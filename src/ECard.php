<?php

namespace CSUInformation;

use CSUInformation\Exception\CurlException;
use CSUInformation\Exception\LoginException;
use CSUInformation\Exception\SessionException;
use CSUInformation\Exception\NoSessionException;
use CSUInformation\Exception\ParseException;

/**
 * 校园卡查询系统
 */
class ECard {
    private $header;
    private $user;

    function __construct($user='', $password='') {
        if(!empty($user) && !empty($password))
            $this->login($user, $password);
    }
    /**
     * 登陆
     * @param  string $user     校园卡账户
     * @param  string $password 校园卡查询密码
     */
    function login($user, $password) {
        $this->user = $user;
        $curl = curl_init ();
        $url = "http://ecard.csu.edu.cn/loginstudent.action";
        $data = "name={$user}&userType=1&passwd={$password}&loginType=1&rand=2181&imageField.x=38&imageField.y=8";
        curl_setopt_array ( $curl, array (
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 2
        ) );
        $result = mb_convert_encoding(curl_exec($curl), 'UTF-8', 'GBK');
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close ( $curl );
        if($errno)
            throw new CurlException($errno, $error);
        if(strpos($result, '信息提示') > 0) {
            // 登陆失败
            preg_match('/class="biaotou" >([^<]+)</', $result, $loginError);
            throw new LoginException($loginError[1]);
        }
        // 匹配session字符串
        if(! preg_match('/JSESSIONID=(.+);/', $result, $session))
            throw new SessionException();
        $this->header = array(
                "Cookie: JSESSIONID=" . $session[1]
            );
    }
    /**
     * 历史流水
     * @param  string $beginDate 开始日期 格式:20150601
     * @param  string $endDate   结束日期
     * @return array             记录
     */
    function getHistory($beginDate, $endDate) {
        $continue = $this->getContinue();
        
        $data = "account={$this->user}&inputObject=all&Submit=+%C8%B7+%B6%A8+";
        $continue = $this->getContinue($continue, $data);
        
        $data = "inputStartDate={$beginDate}&inputEndDate={$endDate}";
        $continue = $this->getContinue($continue, $data);

        $result = $this->getHistoryHtml($continue);
        return $this->parseTable($result);
    }
    /**
     * 当日流水
     * @return array 记录
     */
    function getToday() {
        if(empty($this->header))
            throw new NoSessionException();
        
        $curl = curl_init ();
        $url = "http://ecard.csu.edu.cn/accounttodatTrjnObject.action";
        $data = "account={$this->user}&inputObject=all";
        curl_setopt_array ( $curl, array (
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->header,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 2
        ) );
        $result = mb_convert_encoding(curl_exec($curl), 'UTF-8', 'GBK');
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close ( $curl );
        if($errno)
            throw new CurlException($errno, $error);
        return $this->parseTable($result);
    }
    /**
     * 获取continue的地址
     * @param  string $continue 此continue地址
     * @param  string $data     post表单
     * @return string           下一个continue地址
     */
    private function getContinue($continue='', $data='') {
        $result = $this->getHistoryHtml($continue, $data);
        if(! preg_match('/__continue=(\w+)"/', $result, $continue))
            throw new ParseException("Error finding the '__continue' url");
        return $continue[1];
    }
    /**
     * 获取历史流水的页面
     * @param  string $continue 此页面continue地址
     * @param  string $data     post表单内容
     * @return string           HTML代码
     */
    private function getHistoryHtml($continue='', $data='') {
        if(empty($this->header))
            throw new NoSessionException();
        if($continue)
            $continue = '?__continue=' . $continue;
        $url = "http://ecard.csu.edu.cn/accounthisTrjn.action" . $continue;

        $curl = curl_init ();
        curl_setopt_array ( $curl, array (
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->header,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 2
        ) );
        $result = mb_convert_encoding(curl_exec($curl), 'UTF-8', 'GBK');
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close ( $curl );
        if($errno)
            throw new CurlException($errno, $error);
        return $result;
    }
    /**
     * 解析流水表格
     * @param  string &$content HTML代码
     * @return array            流水记录数组
     */
    private function parseTable(&$content) {
        $pattern = '/<td +align="(?:center|right)" *>([^\s<]*|\d+\/\d+\/\d+ \d+:\d+:\d+) *<\/td>/';
        if(! preg_match_all($pattern, $content, $result))
            return array();
        $result = $result[1];
        array_unshift($result, '日期');
        return array_chunk($result, 10);
    }
}