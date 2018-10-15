<?php
//

//即时到帐支付应答类
//============================================================================
//api说明：
//getKey()/setKey(),获取/设置密钥
//getParameter()/setParameter(),获取/设置参数值
//getAllParameters(),获取所有参数
//isTenpaySign(),是否财付通签名,true:是 false:否
//getDebugInfo(),获取debug信息
//============================================================================

namespace App\Services\Payment\wxpay;
use Log;

class WxResponse
{
    //密钥
    var $key;

    //应答的参数
    var $parameters;

    //debug信息
    var $debugInfo;

    //初始构造函数
    function __construct() {
        $this->RequestHandler();
    }
    function RequestHandler() {
        $this->gateUrl = "https://wpay.tenpay.com/wx_pub/v1.0/wx_app_api.cgi";
        $this->key = "";
        $this->parameters = array();
        $this->debugInfo = "";
    }

    //获取密钥
    function getKey() {
        return $this->key;
    }

    //设置密钥
    function setKey($key) {
        $this->key = $key;
    }

    //获取参数值
    function getParameter($parameter) {
        return $this->parameters[$parameter];
    }

    //设置参数值
    function setParameter($parameter, $parameterValue) {
        $this->parameters[$parameter] = $parameterValue;
    }
    //清空参数值
    function clearParameter(){
        return $this->$parameters->RemoveAll;
    }
    //获取所有请求的参数,返回Scripting.Dictionary
    function getAllParameters() {
        return $this->parameters;
    }

    /**
     * xml 转换成数组
     * @param string $xml
     * @return array
     */
    function xmlToArray($xml)
    {
        $xmlObj = simplexml_load_string(
            $xml,
            'SimpleXMLIterator',   //可迭代对象
            LIBXML_NOCDATA
        );

        $arr = [];
        $xmlObj->rewind(); //指针指向第一个元素
        while (1) {
            if( ! is_object($xmlObj->current()) )
            {
                break;
            }
            $arr[$xmlObj->key()] = $xmlObj->current()->__toString();
            $xmlObj->next(); //指向下一个元素
        }

        return $arr;
    }


    /**
     *是否财付通签名,规则是:按参数名称a-z排序,遇到空值的参数不参加签名。
     *true:是
     *false:否
     */
    function isTenpaySign() {
        $signPars = "";
        ksort($this->parameters);

        foreach($this->parameters as $k => $v) {
            if("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $this->getKey();

        $sign = strtolower(md5($signPars));

        $tenpaySign = strtolower($this->getParameter("sign"));
        
        return $sign == $tenpaySign;

    }

    //获取debug信息
    function getDebugInfo() {
        return $this->debugInfo;
    }

    function setDebugInfo($debug) {
        $this->debugInfo=$debug;
    }

    /**
     * 取成功响应
     * @return string
     */
    public function getSucessXml()
    {
        $xml = '<xml>';
        $xml .= '<return_code><![CDATA[SUCCESS]]></return_code>';
        $xml .= '<return_msg><![CDATA[OK]]></return_msg>';
        $xml .= '</xml>';
        return $xml;
    }

    public function getFailXml()
    {
        $xml = '<xml>';
        $xml .= '<return_code><![CDATA[FAIL]]></return_code>';
        $xml .= '<return_msg><![CDATA[OK]]></return_msg>';
        $xml .= '</xml>';
        return $xml;
    }

}
?>
