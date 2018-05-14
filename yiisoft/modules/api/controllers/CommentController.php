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
        $this->enableCsrfValidation=false;
    }
    public function actionSetCommentSupportRate() {
        // 支持或反对, 指明类型，指定
        if($body = $this->checkParams(['type', 'comment_id'], 'post')) {
            if ($body['type'] === 'agree') {
                $this->redis->hincrby('VproCommentAgree', $body['comment_id'], 1);
            } else {
                $this->redis->hincrby('VproCommentOppose', $body['comment_id'], 1);
            }
        }
    }

    /**
     * 去取得评论，但是评论不一定每篇都有，如果频繁刷新，数据库会崩溃，所以需要放入一个临时值
     * 拿到评论需要迭代每一条评论的关系，迭代他们的关系
     * redis存放评论的格式应该是[hash] VproComment_[lesson_id], [hkey]comment_id: [hvalue]comment_info
     * 同时设置这个评论表对应的过期时间VproComment_[lesson_id]_expired
     * @return string
     */
//    public function actionGetComment() {
//        $body = $this->checkParams(['lesson_id'], 'get');
//        if ($body) {
//            if ($this->checkRedisKey($body['lesson_id'], 'VproComment') && $this->checkExpired('lesson_id', 'VproComment')) {
//                return $this->hgetex('VproComment', $body['lesson_id']);
//            } else {
//                $res = VproComment::find(['vpro_comment_lesson_id' => $body['lesson_id']])->asArray()->all();
//                $this->hsetex('VproComment', $body['lesson_id'], $this->expired_time(3, 10), json_encode($res));
//                return json_encode($this->returnInfo($res));
//            }
//        } else {
//            return json_encode($this->returnInfo(false, 'PARAMS_ERROR'));
//        }
//    }
    public function actionGetComment() {
        $body = $this->checkParams(['lesson_id'], 'get');
        if ($body) {
            if ($this->checkRedisKey('VproComment_' . $body['lesson_id']) && $this->checkExpired('VproComment_' . $body['lesson_id'])) {
               return $this->redis->hgetall('VproComment_' . $body['lesson_id']);
            } else {
                $res = VproComment::find(['vpro_comment_lesson_id' => $body['lesson_id']])->orderBy('vpro_comment_time desc')->asArray()->all();
                $res = $this->genCommentRelations($res);
//                $j_res = [];
//                foreach($res as $r) {
//                    array_push($j_res, )
//                }
//                $this->redis->hmset('VproComment_' . $body['lesson_id'], ...$res);
//                return json_encode($this->returnInfo($res));
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
            if(intval($item['vpro_comment_reply_id']) === 0) {
                array_push($res, [$item['vpro_comment_id'] => $item]);
            } else {
                // 迭代
                $arr = $this->iterRelations($data, $item, [$item]);
                array_push($res, [$item['vpro_comment_id'] => $arr]);
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

    public function actionGetCommentSupportRate() {
        $body = $this->checkParams(['comment_ids'], 'post');
        if ($body) {
            $res = [];
            $agree = $this->redis->hmget('VproCommentAgree', ...$body['comment_ids']);
            $oppose = $this->redis->hmget('VproCommentOppose', ...$body['comment_ids']);
            foreach($agree as $key => $value) {
                array_push($res, [$value === null ? 0 : $value, $oppose[$key] === null ? 0 : $oppose[$key]]);
            }
            return json_encode($this->returnInfo($res));
        } else {
            return json_encode($this->returnInfo(false, 'PARAMS_ERROR'));
        }
    }

    /**
     * 将评论数据发给放到redis的VproCommentList中，python进行消息队列处理
     * @return string
     */
    public function actionSetComment() {
//        传入格式：{comment_course_id: x, comment_lesson_id: xx, comment_reply_id: '', comment_reply_main_id: ''}
        $body = $this->checkParams(['comment_course_id', 'comment_lesson_id', 'comment_reply_id', 'comment_reply_main_id', 'comment_content', 'user_id'], 'post');
        if ($body) {
            $body['vpro_comment_time'] = time();
            $res = $this->redis->lpush('VproCommentList', json_encode($body));
            $ret = $res ? 'RETURN_SUCCESS' : 'PUSH_ERROR';
            return json_encode($this->returnInfo($res, $ret));
        } else {
            return json_encode($this->returnInfo(false, 'PARAMS_ERROR'));
        }
    }
}