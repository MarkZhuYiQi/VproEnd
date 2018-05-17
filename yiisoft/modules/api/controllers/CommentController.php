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
        $body = $this->checkParams(['comment_ids'], 'post');
        if ($body) {
            $res = [];
            $agree = $this->redis->hMGet('VproCommentAgree', $body['comment_ids']);
            $oppose = $this->redis->hMGet('VproCommentOppose', $body['comment_ids']);
            foreach($agree as $key => $value) {
                array_push($res, [$value === false ? 0 : $value, $oppose[$key] === false ? 0 : $oppose[$key]]);
            }
            $res = array_combine($body['comment_ids'], $res);
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
        // 支持或反对, 指明类型，指定
        if ($body = $this->checkParams(['type', 'comment_id', 'lesson_id'], 'post')) {
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
            foreach(array_values($ret) as $r) {
                if ($r) {
                    continue;
                } else {
                    return $this->returnInfo('data operate error', 'REDIS_OPERATE_ERROR');
                }
            }
            $this->returnInfo('1');
        } else {
            $this->returnInfo('params transfer error', 'PARAMS_ERROR');
        }
    }

    /**
     * 去取得评论，但是评论不一定每篇都有，如果频繁刷新，数据库会崩溃，所以需要放入一个临时值
     * 拿到评论需要迭代每一条评论的关系，迭代他们的关系
     * redis存放评论的格式应该是[hash] VproComment_[lesson_id], [hkey]comment_id: [hvalue]comment_info
     * 同时设置这个评论表对应的过期时间VproComment_[lesson_id]_expired
     * @return string
     */
    public function actionGetComment()
    {
        $body = $this->checkParams(['lesson_id'], 'get');
        if ($body) {
            $comments_json = 'VproComment_' . $body['lesson_id'] . '_json';
            $comments = 'VproComment_' . $body['lesson_id'];
            if ($this->checkRedisKey($comments_json) && $this->checkRedisKey($comments)) {
                return json_encode($this->returnInfo(
                    [
                        'comments' => json_decode($this->redis->get($comments_json)),
                        'comment_ids' => $this->redis->hKeys($comments)
                    ]
                ));
            } else {
                $res = VproComment::find(['vpro_comment_lesson_id' => $body['lesson_id']])->orderBy('vpro_comment_time desc')->asArray()->all();
                // 有评论返回数组，没有评论返回空数组
                // [[comment_id=>xxx, ..., parent=>[(这里是这评论上面的父级排列评论)], ...], [...], [...]]
                $res = $this->genCommentRelations($res);
                $j_res = [];
                $comments_key = [];
                if (count($res)) {
                    foreach ($res as $r) {
                        array_push($comments_key, $r['vpro_comment_id']);
                        $j_res[$r['vpro_comment_id']] = json_encode($r);

                    }
                } else {
                    // 如果没有任何回复，就给redis存入空字符串
                    $res = "";
                }
                // 涉及多次操作时应该使用事务！
                $this->redis->multi()
                    // 这里创建了空评论列表
                    ->hMSet($comments, $j_res)
                    ->set($comments_json, json_encode($res))
                    ->exec();
                return json_encode($this->returnInfo(
                    [
                        'comments' => $res,
                        'comment_ids' => $comments_key
                    ]
                ));
            }
        }
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
     * 2个数据，1个是回复评论用的维护hash表，一个是string类型的课时评论json
     *
     * 根据该条评论是否是回复：
     *      是回复，hget(VproComment_xxx, comment_reply_id), 得到结果[comment_id: xxx, ..., parent: [[],[],...]]
     *          将该结果parent外的信息放到parent里面，全部成为新评论的parent
     *          然后塞进新评论的parent中
     *          将新评论放入hash表VproComment_(LESSON_ID) hkey: comment_id, hvalue: 以上结果
     *      不是回复：
     *          直接将评论放到VproComment_(LESSON_ID)
     *
     * 取出VproComment_(LESSON_ID)_json, 将最新一条评论push进去，然后再存起来，作为文章的最终评论展示
     *
     * 如果以上VproComment_(LESSON_ID)_json和(VproComment_(LESSON_ID)没有，那么直接塞进数据库就完事儿了，因为前台访问的时候会生成
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