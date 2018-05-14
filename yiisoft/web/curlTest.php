<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 2/6/2017
 * Time: 2:10 PM
 */

$url='http://localhost/reactBlog/yiisoft/web/index.php/infos';
$vars=['access-token'=>'7777777y'];
$string='';
foreach($vars as $key =>$value)
{
    if($string==''){
        $string.='?';
    }else{
        $string.='&';
    }
    $string.=$key.'='.$value;
}
$ch=curl_init($url.$string);
$header=array(
//    'Accept:application/xml',
//    'Content-Type:application/xml',
    'Accept:application/json',
);
$post_data=['user_name'=>'test','user_pass'=>'test','user_id'=>66];
curl_setopt($ch,CURLOPT_HEADER,true);
curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$post_data);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);  //不直接输出
$res=curl_exec($ch);
curl_close($ch);
var_export($res);