<?php
namespace api\controllers;
use api\controllers\ShoppingBaseController;
use app\models\ModelFactory;
use app\models\VproComment;
use Yii;

class CommentController extends ShoppingBaseController
{
//------------------------------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------------------------------
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation = false;
    }

    /**
     * 获得评论
     * 从VproCommentAgree和VproCommentOppose根据id hmget需要的值， 组合成数组返还给前端
     * @return string
     */
    public function actionGetCommentSupportRate() {
        $body = $this->checkParams(['comments_ids'], 'post');
        if ($body) {
            $res = [];
            $agree = $this->redis->hMGet('VproCommentAgree', $body['comments_ids']);
            $oppose = $this->redis->hMGet('VproCommentOppose', $body['comments_ids']);
            foreach($agree as $key => $value) {
                array_push($res, [$value === false ? 0 : $value, $oppose[$key] === false ? 0 : $oppose[$key]]);
            }
            $res = array_combine($body['comments_ids'], $res);
            return json_encode($this->returnInfo($res));
        } else {
            return json_encode($this->returnInfo(false, 'PARAMS_ERROR'));
        }
    }
    /**
     * 点击支持或者反对后，一方面在HASH表里设置该评论的支持反对率（+1）：
     * [VproCommentAgree: {1: x, 2: y, 3: z, 4: q, ......}]
     * [VproCommentOppose: {1: x, 2: y, 3: z, 4: q, ......}]
     * 另一方面给集合(SET)放入一个评论comment_id，用于在同步到数据库,
     * VproCommentSupportRate_agree: {1234, 5678, 6543, xxxx, ....}, VproCommentSupportRate_oppose: {5432,25346,67876534,1232,12,......},
     * 里面存储需要更新的comment_id
     * @return array
     */
    public function actionSetCommentSupportRate()
    {
        $body = $this->checkParams(['type', 'comment_id', 'lesson_id'], 'get');
        // 支持或反对, 指明类型，指定
        if ($body)json_encode($this->returnInfo('params transfer error', 'PARAMS_ERROR'));
        $ret = [];
        if ($body['type'] === 'agree') {
            $ret = $this->redis->multi()
                ->hIncrBy('VproCommentAgree', $body['comment_id'], 1)
                ->sAdd('VproCommentSupportRate_agree', $body['comment_id'])
                ->exec();
        } else {
            $ret = $this->redis->multi()
                ->hIncrBy('VproCommentOppose', $body['comment_id'], 1)
                ->sAdd('VproCommentSupportRate_oppose', $body['comment_id'])
                ->exec();
        }
        return json_encode($this->returnInfo('1'));
    }

    /**
     * 去取得评论，但是评论不一定每篇都有，如果频繁刷新，数据库会崩溃，所以需要放入一个临时值
     * 拿到评论需要迭代每一条评论的关系，迭代他们的关系
     * redis存放评论的格式应该是[hash] VproComment_[lesson_id], [hkey]comment_id: [hvalue]comment_info
     * redis存放评论到list表VproComment_(lesson_id)_list
     * @return string
     */
    public function actionGetComment()
    {
        // 2个参数，课程id以及评论页码
        $body = $this->checkParams(['lesson_id', 'p'], 'get');
        if ($body) {
            // redis里的list表名称
            $comments_push_list = 'VproComment_' . $body['lesson_id'] . '_list';
            // redis里的hash表名称
            $comments = 'VproComment_' . $body['lesson_id'];
            $comments_start = $this->params['COMMENTS_COUNT'] * ($body['p'] - 1);
            $comments_end = $this->params['COMMENTS_COUNT'] * $body['p'];
            if (!$this->checkRedisKey($comments_push_list) || !$this->checkRedisKey($comments)) {
                $res = VproComment::find()
                    ->select(['vpro_comment.*','vpro_auth.auth_appid'])
                    ->where(['vpro_comment_lesson_id' => $body['lesson_id']])
                    ->joinWith('vproAuth', false)
                    ->orderBy('vpro_comment_time desc')
                    ->asArray()
                    ->all();
                // 有评论返回数组，没有评论返回空数组
                // 返回格式：[[comment_id=>xxx, ..., parent=>[(这里是这评论上面的父级排列评论)], ...], [...], [...]]
                $res = $this->genCommentRelations($res);
                // 键值对， [vpro_comment_id => [xxx], vpro_comment_id => [xxx]]
                $j_res = [];
                // 用于list的值，直接放入每个评论对象，没有键
                $l_res = [];
                $comments_key = [];
                if (count($res)) {
                    foreach ($res as $r) {
                        // 组成了[comment_id => obj]形式的
                        $j_res[$r['vpro_comment_id']] = json_encode($r);
                        array_push($l_res, json_encode($r));
                    }
                } else {
                    // 如果没有任何回复，就给redis存入空字符串
                    $res = "";
                }
                // 塞入评论列表，如果有评论，塞入评论，没有就设置空值
                if (count($j_res) > 0)
                {
                    // 涉及多次操作时应该使用事务！
                    $this->redis->multi()
                        // 这里创建了评论列表hash
                        ->hMSet($comments, $j_res)
                        // 这里是设置list
                        ->rPush($comments_push_list, ...$l_res)
                        ->exec();
                } else {
                    $this->redis->hMSet($comments, $j_res);
                }

            }
            return $this->getCommentsByRedis($comments_push_list, $comments, $comments_start, $comments_end);
        }
        return json_encode($this->returnInfo('params transfer error', 'PARAMS_ERROR'));
    }
    private function getCommentsByRedis($comments_push_list, $comments, $comments_start, $comments_end) {
        $comments = $this->redis->lRange($comments_push_list, $comments_start, $comments_end);
        $comments_ids = [];
        if ($comments !== '') {
            foreach($comments as $key => $value) {
                $comments[$key] = json_decode($value);
                $comments_ids[] = $comments[$key]->vpro_comment_id;
            }
        }
        return json_encode($this->returnInfo(
            [
                'comments'          => $comments,
                'comments_ids'       => $comments_ids,
                'comments_length'    => $this->redis->lLen($comments_push_list)
            ]
        ));
    }
    /**
     * $data是原始数据
     * 返回的是子评论数组
     * @param $data
     * @return array
     */
    private function genCommentRelations($data) {
        if (count($data) <= 0) return [];
        $res = [];
        foreach($data as $item) {
            // 如果是主评论，直接放进数组中
            if(intval($item['vpro_comment_reply_id']) === 0) {
                array_push($res, $item);
            } else {
                // 如果是一个回复评论，需要去往上找爹
                $item['parent'] = [];
                $item['parent'] = $this->iterRelations($data, $item, []);
                array_push($res, $item);
            }
        }
        return $res;
    }

    /**
     *
     * @param array $data           origin data
     * @param array $item           the comment which contains parent
     * @param array $res            comment List
     * @return array                comment list
     */
    private function iterRelations($data, $item, $res=[]) {
        $flag = false;
        $newItem = [];
        foreach($data as $d) {
            // 找到parent，将parent放进res结果中
            if (intval($d['vpro_comment_id']) === intval($item['vpro_comment_reply_id'])) {
                array_unshift($res, $d);
                if (intval($d['vpro_comment_reply_id']) > 0) {
                    $flag = true;
                    $newItem = $d;
                }
                break;
            }
        }
        if ($flag) {
            $res = $this->iterRelations($data, $newItem, $res);
        }
        return $res;

    }

    /**
     * 将评论数据发给放到redis的VproCommentList中，python进行消息队列处理
     * 消息队列在将评论放到mysql的同时，需要将评论放入redis，供前端读取
     * 2个数据，1个是回复评论用的维护hash表，一个是list类型的每节课的评论表，用于评论显示和分页
     *
     * 根据该条评论是否是回复：
     *      是回复，hget(VproComment_xxx, comment_reply_id), 得到结果[comment_id: xxx, ..., parent: [[],[],...]]
     *          将该结果parent外的信息放到parent里面，全部成为新评论的parent
     *          然后塞进新评论的parent中
     *          将新评论放入hash表VproComment_(LESSON_ID) hkey: comment_id, hvalue: 以上结果
     *      不是回复：
     *          直接将评论放到VproComment_(LESSON_ID)
     *
     * 将最新的评论塞进hash表VproComment_(lesson_id) 以及 list表VproComment_(lesson_id)_list
     *
     * 如果以上VproComment_(LESSON_ID)_list和(VproComment_(LESSON_ID)没有，那么直接塞进数据库就完事儿了，因为前台访问的时候会生成
     * -----------------------------------------------------------------------------------------------------------------
     * 关于评论的分页，思路：
     * 按照每节课的粒度区分：
     *      1. 按照lesson_id区分
     *
     * 每节课，按照页的粒度来分缓存
     *      1.  首次生成缓存时，一口气读取某节课下的所有评论
     *      2.  根据依赖关系生成每一条评论放入array中。
     *      3.  循环数组，将数组中所有元素统统放入redis的VproCommentList。
     *      4.  将数组推进list中，用于分页，使用lrange根据llen分页。
     *      5.  后期可以定期指定crond任务，如果list非常大，那么势必需要切分
     *
     * @return string
     */
    public function actionSetComment() {
//        传入格式：{comment_course_id: x, comment_lesson_id: xx, comment_reply_id: '', comment_reply_main_id: ''}
        $body = $this->checkParams(['comment_course_id', 'comment_lesson_id', 'comment_reply_id', 'comment_reply_main_id', 'comment_content', 'user_id'], 'post');
        if ($body) {
            $body['vpro_comment_time'] = time();
            $res = $this->redis->lPush('VproCommentList', json_encode($body));
            $ret = $res ? 'RETURN_SUCCESS' : 'PUSH_ERROR';
            return json_encode($this->returnInfo($res, $ret));
        } else {
            return json_encode($this->returnInfo(false, 'PARAMS_ERROR'));
        }
    }
}