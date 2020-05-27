<?php
namespace ZfSpider;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Promise;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;

use stdClass;
use ZfSpider\Traits\Parser;
use ZfSpider\Traits\BuildRequest;

class Client
{
    use Parser, BuildRequest;

    const ZF_GRADE_URI = 'xscj_gc.aspx';

    const ZF_EXAM_URI = 'xskscx.aspx';

    const ZF_SCHEDULE_URI = 'xskbcx.aspx';

    const ZF_CET_URI = 'xsdjkscx.aspx';

    const ZF_SELECT_URI = 'xsxkqk.aspx';

    const ZF_DETAIL_URI = 'xsgrxx.aspx';

    public $client;

    private $base_uri;

    private $login_uri = 'default_vsso.aspx';

    private $main_page_uri = 'xs_main.aspx';

    private $headers = [
        'timeout' => 3.0,
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Content-Type' => 'application/x-www-form-urlencoded'
    ];

    private $stu_id;

    private $password;

    private $cache; //Doctrine\Common\Cache\Cache

    private $cachePrefix = 'ZfSpider';

    //The login post param
    private $loginParam = [];

    private $cookie_vpn;

    private $cookie;

    private $vpn_url;

    /**
     * @param string $username
     * @param string $password
     * @return Client
     * @throws SpiderException
     */
    public function login_vpn(string $username, string $password){
        $post=[
            'uname'=>$username,
            'pwd'=>$password,
            'method'=>"",
            'pwd1'=>$password,
            'pwd2'=>"",
            'submitbutton'=>urldecode("%E7%99%BB%E5%BD%95"),
        ];
        $query = [
            'form_params' => $post,
            'cookies' => $this->cookie_vpn,
            'allow_redirects' => false
        ];
        $result = $this->client->request('POST', $this->vpn_url.'login', $query);
        if($result->getStatusCode() == 302 and $result->getHeader("Location") == ['https://vpn.bjut.edu.cn/prx/000/http/localhost/welcome']) {
            return $this;
        }
        throw new SpiderException("VPN登录失败!");
    }

    /**
     * Test if vpn is logged in.
     * @return bool
     */
    public function test_vpn(){
        $result = $this->client->get($this->vpn_url.'welcome', [
            'allow_redirects' => false,
            'cookies' => $this->cookie_vpn
        ]);
        if($result->getStatusCode() == 200) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Client constructor.
     * @param $user
     * @param array $loginParam
     * @param string $vpn_url
     * @param string $base_url
     * @param array $request_optionss
     * @throws SpiderException
     */
    function __construct($user, $loginParam = [], $vpn_url = "https://vpn.bjut.edu.cn/prx/000/http/localhost/", $base_url = "https://vpn.bjut.edu.cn/prx/000/http/gdjwgl.bjut.edu.cn/", $request_options = [])
    {
        //Set the base_uri.
        $this->vpn_url = $vpn_url;
        $this->base_uri = $base_url;

        //Set the stu_id and password
        $this->stu_id = $user['stu_id'];
        $this->password = $user['stu_pwd'];

        $this->request_options = $request_options;

        $client_param = [
            // Base URI is used with relative requests
            'base_uri' => $this->base_uri
        ];

        $this->cookie_vpn = new CookieJar();

        //Set the login post param
        if (!empty($loginParam)) {
            $this->loginParam = $loginParam;
        }

        $this->client = new HttpClient($client_param);
    }

    /**
     * @return CookieJar
     */
    public function getCookieJar(){
        return $this->cookie;
    }

    /**
     * @param CookieJar $jar
     * @return Client
     */
    public function setCookieJar(CookieJar $jar){
        $this->cookie = $jar;
        return $this;
    }

    /**
     * @return CookieJar
     */
    public function getCookieVpnJar(){
        return $this->cookie_vpn;
    }

    /**
     * @param CookieJar $jar
     * @return Client
     */
    public function setCookieVpnJar(CookieJar $jar){
        $this->cookie_vpn = $jar;
        return $this;
    }

    /**
     * Set the UserAgent.
     *
     * @param string $ua
     * @return Object $this
     */
    public function setUa($ua)
    {
        $this->headers['User-Agent'] = $ua;
        return $this;
    }

    /**
     * Get the User-Agent value.
     *
     * @return string
     */
    public function getUa()
    {
        return $this->headers['User-Agent'];
    }

    /**
     * Set the Timeout.
     *
     * @param float $time
     * @return Client
     */
    public function setTimeOut($time)
    {
        if (!is_numeric($time)) {
            //Should throw a Exception?
            return $this;
        }
        $this->headers['timeout'] = $time;
        return $this;
    }

    /**
     * Get the Timeout.
     *
     * @return string
     */
    public function getTimeOut()
    {
        return $this->headers['timeout'];
    }

    /**
     * Set the Login uri.
     *
     * @param string $uri
     * @return string
     */
    public function setLoginUri($uri)
    {
        $this->login_uri = $uri;
        return $this;
    }

    /**
     * Get the login uri.
     *
     * @return string
     */
    public function getLoginUri()
    {
        return $this->login_uri;
    }

    /**
     * Set the Referer header.
     *
     * @param string $referer
     * @return string
     */
    public function setReferer($referer)
    {
        $this->headers['referer'] = $referer;
        return $this;
    }

    /**
     * Get the Referer header.
     *
     * @return string
     */
    public function getReferer()
    {
        return $this->headers['Referer'];
    }

    /**
     * Set the main page uri, the default value is 'xs_main.aspx'
     *
     * @param string $uri
     * @return Client
     */
    public function setMainPageUri($uri)
    {
        $this->main_page_uri = $uri;
        return $this;
    }

    /**
     * Get the main page uri, the default value is 'xs_main.aspx'
     *
     * @return string
     */
    public function getMainPageUri()
    {
        return $this->main_page_uri;
    }

    /**
     * Login, and get the cookie jar.
     *
     * @param void
     * @return $this or $jar
     * @throws SpiderException
     */
    public function login()
    {
        if(!$this->test_vpn()){
            throw new SpiderException("未登录vpn或已经过期!");
        }
        $this->cookie = clone $this->cookie_vpn;

        //Get the hidden value from login page.
        $loginParam = [
            'stu_id' => 'TextBox1',
            'passwod' => 'TextBox2',
        ];
        if (!empty($this->loginParam)) {
            $loginParam = $this->loginParam;
        }

        $form_params = [
            $loginParam['stu_id'] => $this->stu_id,
            $loginParam['passwod'] => $this->password,
        ];

        $query = [
            'form_params' => $form_params,
            'cookies' => $this->cookie,
            'allow_redirects' => false,
        ];
        $result = $this->client->request('POST', $this->login_uri, $query);
        $response = $this->client->get($this->main_page_uri, [
            'allow_redirects' => false, 'query' => ['xh' => $this->stu_id],
            'cookies' => $this->cookie,
        ]);
        switch ($response->getStatusCode()) {
            case 200:
                return $this;
                break;
            case 302:
                throw new SpiderException('Wrong password.', 1);
                break;
            default:
                throw new SpiderException('Maybe the data source is broken!', 1);
                break;
        }
    }

    /**
     * Get the grade data. This function is request all of grade.
     *
     * @return array
     */
    public function getGrade()
    {
        //Get the hidden value.
        $response = $this->get(self::ZF_GRADE_URI, [], $this->headers);
        $viewstate = $this->getGradeViewState($response->getBody());
        $post['__VIEWSTATE'] = $viewstate;
        $post['Button1'] = '%B2%E9%D1%AF%D2%D1%D0%DE%BF%CE%B3%CC%D7%EE%B8%DF%B3%C9%BC%A8';
        $response = $this->post(self::ZF_GRADE_URI, [], $post, $this->headers);
        $data = $this->getCommonTable($response->getBody(), '#Datagrid1');
        $newdata = [];
        foreach($data as $d){
            $newdata[] = [
                'year' => $d[0],
                'term' => $d[1],
                'courseId' => $d[2],
                'courseName' => $d[3],
                'courseType' => $d[4],
                'courseBelong' => $d[5],
                'courseCredit' => $d[6],
                'gradePoint' => $d[7],
                'score' => $d[8],
                'minorMark' => $d[9],
                'makeUpScore' => $d[10],
                'retakeMark' => $d[14],
                'retakeScore' => $d[11],
                'academy' => $d[12],
                'remark' => $d[13]
            ];
        }
        return $newdata;
    }

    /**
     * Get the schedule data
     *
     * @param string $term
     * @param string $year
     * @return array
     */
    public function getSchedule()
    {
        $response = $this->get(self::ZF_SCHEDULE_URI, [], $this->headers);
        return $this->getScheduleTable($response->getBody());
    }

    /**
     * Get the default term exam data by GET.
     *
     * @return array
     */
    public function getExams()
    {
        $response = $this->get(self::ZF_EXAM_URI);
        $data = $this->getCommonTable($response->getBody());
        $newData = [];
        foreach($data as $d){
            $newData[] = [
                'courseId'=>$d[0],
                'courseName'=>$d[1],
                'name'=>$d[2],
                'dateTime'=>$d[3],
                'classroom'=>$d[4],
                'type'=>$d[5],
                'seat'=>$d[6],
                'campus' => $d[7],
            ];
        }
        return $newData;
    }

    /**
     * Get the CET-4/6 result.
     *
     * @return array
     */
    public function getCet()
    {
        $response = $this->get(self::ZF_CET_URI);
        return $this->cetData($response->getBody());
    }

    /**
     * Get the course select result.
     *
     * @return array
     */
    public function getCourseSelect()
    {
        $response = $this->get(self::ZF_SELECT_URI);
        return $this->courseSelectData($response->getBody());
    }
}
