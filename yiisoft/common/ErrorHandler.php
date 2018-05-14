<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/12/2018
 * Time: 4:45 PM
 */
namespace app\common;
class ErrorHandler{
    protected $_errorHandlerModelName = '';
    protected $_errorHandlerModel;
    public function saveByErrorHandler($id){
        return $id;
    }
}