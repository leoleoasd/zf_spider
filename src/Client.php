<?php
namespace ZfSpider;

use ArrayObject;
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

    protected $stu_id;
    protected $password;
    protected $logged_in = false;
    protected $logged_in_vpn = false;

    private $cache; //Doctrine\Common\Cache\Cache

    private $cachePrefix = 'ZfSpider';

    //The login post param
    private $loginParam = [];

    private $cookie;

    private $vpn_url;

    private $responses = [];

    /**
     * @param string $username
     * @param string $password
     * @return CookieJar
     * @throws SpiderException
     */
    public function login_vpn(string $username, string $password){
        $post=[
            'uname'=>$username,
            'pwd'=>$password,
            'method'=>'',
            'pwd1'=>$password,
            'pwd2'=>'',
            'submitbutton'=>urldecode('%E7%99%BB%E5%BD%95'),
        ];
        $query = [
            'form_params' => $post,
            'cookies' => $this->cookie,
            'allow_redirects' => false
        ];
        $result = $this->client->request('POST', $this->vpn_url.'login', $query);
        if($result->getStatusCode() == 302 and $result->getHeader('Location') == ['https://vpn.bjut.edu.cn/prx/000/http/localhost/welcome']) {
            $this->logged_in_vpn = true;
            return $this->cookie;
        }
        throw new SpiderException( 'VPN登录失败!', 1010 );
    }

    /**
     * Test if vpn is logged in.
     * @return bool
     */
    public function test_vpn(){
        $result = $this->client->get($this->vpn_url.'welcome', [
            'allow_redirects' => false,
            'cookies' => $this->cookie
        ]);
        if( $result->getStatusCode() == 200 ) {
            $this->logged_in_vpn = true;
            return true;
        } else {
            $this->logged_in_vpn = false;
            return false;
        }
    }

    /**
     * Client constructor.
     * @param $user
     * @param array $loginParam
     * @param string $vpn_url
     * @param string $base_url
     * @param array $request_options
     */
    function __construct($user, $loginParam = [], $vpn_url = 'https://vpn.bjut.edu.cn/prx/000/http/localhost/', $base_url = 'https://vpn.bjut.edu.cn/prx/000/http/gdjwgl.bjut.edu.cn/', $request_options = [])
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

        $this->cookie = new CookieJar();

        //Set the login post param
        if (!empty($loginParam)) {
            $this->loginParam = $loginParam;
        }

        $this->client = new HttpClient($client_param);
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
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->logged_in;
    }

    /**
     * @return bool
     */
    public function isLoggedInVpn(): bool
    {
        return $this->logged_in_vpn;
    }

    /**
     * Login, and get the cookie jar.
     *
     * @param void
     * @throws SpiderException
     * @return CookieJar
     */
    public function login()
    {
        /* if(!$this->test_vpn()){
            throw new SpiderException('未登录vpn或已经过期!');
        } */

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
        $response = $this->client->request('POST', $this->login_uri, $query);
        switch ($response->getStatusCode()) {
            case 302:
                if(strstr($response->getHeader('Location')[0], 'err')) {
                    throw new SpiderException('Wrong password.', 1003);
                }
                if(strstr($response->getHeader('Location')[0],'fk_main.html')) {
                    throw new SpiderException('Wrong username.', 1002);
                }
                break;
            default:
                throw new SpiderException('Maybe the data source is broken!', 1001);
                break;
        }
        $this->logged_in = true;
        return $this->cookie;
    }

    /**
     * Get the grade data. This function is request all of grade.
     *
     * @return stdClass
     */
    public function getGrade()
    {
        $response = $this->get(self::ZF_GRADE_URI);
        $data = $this->getCommonTable($response->getBody(), '#Datagrid1');
        if(is_null($data)) { return null; }
        $n = new stdClass();
        $n->grade_term = new ArrayObject();
        foreach($data as $k => $v){
            $n->grade_term[$k] = new stdClass();
            $n->grade_term[$k]->year = $v[0];
            $n->grade_term[$k]->term = $v[1];
            $n->grade_term[$k]->id = $v[2];
            $n->grade_term[$k]->name = str_replace(
                ['Ⅰ', 'Ⅱ',  'Ⅲ',  'Ⅳ',  'Ⅴ', 'Ⅵ', 'Ⅶ',  'Ⅷ',   'Ⅸ', 'Ⅹ', 'Ⅺ',  'Ⅻ'],
                ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'], $v[3]);
            $n->grade_term[$k]->type = $v[4];
            $n->grade_term[$k]->belong = $v[5];
            $n->grade_term[$k]->credit = $v[6];
            $n->grade_term[$k]->gpa = $v[7];
            $n->grade_term[$k]->score = $v[8];
            $n->grade_term->grade_term[$k]->minor_maker = $v[9];
            $n->grade_term[$k]->makeup_score = $v[10];
            $n->grade_term[$k]->retake_maker = $v[14];
            $n->grade_term[$k]->retake_score = $v[11];
            $n->grade_term[$k]->academy = $v[12];
            $n->grade_term[$k]->comment = $v[13];
        }
        return $n;
    }

    /**
     * Get the schedule data
     *
     * @return array
     */
    public function getSchedule()
    {
        $response = $this->get(self::ZF_SCHEDULE_URI);
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
        $data = $this->getCommonTable($response->getBody(), '#DataGrid1');
        if(is_null($data)) { return null; }
        $newData = [];
        foreach($data as $k => $v){
            $newData[$k] = new stdClass();
            $newData[$k]->id = $v[0];
            $newData[$k]->courseName = $v[1];
            $newData[$k]->name = $v[2];
            $newData[$k]->time = $v[3];
            $newData[$k]->room = $v[4];
            $newData[$k]->type = $v[5];
            $newData[$k]->seat = $v[6];
            $newData[$k]->campus = $v[7];
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
     * @param string $year
     * @param string $term
     * @return stdClass
     */
    public function getCourseSelect($year = '', $term = '')
    {
        if ( isset($this->responses['course_select']) ) {
            $response = $this->responses['course_select'];
        } else {
            $response = $this->get(self::ZF_SELECT_URI);
            $this->responses['course_select'] = $response;
        }
        if ( is_null($response) ) {
            return null;
        }

        $viewState = $this->getCourseSelectViewState($response->getBody());
        if ( is_null($viewState) ) {
            return null;
        }

        $ret = new stdClass();
        $ret->year = $viewState[1];
        $ret->term = $viewState[2];
        if ($year == '' || ($ret->year == $year && $ret->term == $term) ) {
            $ret->list = $this->courseSelectData($response->getBody());
            return $ret;
        } else if ($term == '') {
            return null;
        }

        $response = $this->post(self::ZF_SELECT_URI, [], [
            '__VIEWSTATE' => $viewState[0],
            '__EVENTTARGET' => 'ddlXQ',
            'ddlXN' => $year,
            'ddlXQ' => $term,
        ]);
        $viewState = $this->getCourseSelectViewState($response->getBody());
        $ret->year = $viewState[1];
        $ret->term = $viewState[2];
        $ret->list = $this->courseSelectData($response->getBody());
        return $ret;
    }

    /**
     * Get the user detail.
     *
     * @return stdClass
     */
    public function getDetail()
    {
        $response = $this->get(self::ZF_DETAIL_URI);
        return $this->detailData($response->getBody());
    }
}
