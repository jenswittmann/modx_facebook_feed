<?php
require_once __DIR__.'/../Facebook/autoload.php';

class Feed {
  /**
   * A reference to the modX object.
   * @var modX $modx
   */
  public $modx = null;

  /**
   * The Facebook Application Secret
   */
  protected $app_secret;
  protected $access_token;

  public $config;

  function __construct(modX &$modx,array $config = array()) {
    $this->modx =& $modx;

    /* allows you to set paths in different environments
     * this allows for easier SVN management of files
     */
    $corePath = $this->modx->getOption('facebook_feed.core_path',null,$modx->getOption('core_path').'components/facebook_feed/');
    $assetsPath = $this->modx->getOption('facebook_feed.assets_path',null,$modx->getOption('assets_path').'components/facebook_feed/');
    $assetsUrl = $this->modx->getOption('facebook_feed.assets_url',null,$modx->getOption('assets_url').'components/facebook_feed/');

    $this->config = array_merge(array(
      'corePath' => $corePath,
      'modelPath' => $corePath.'model/',
      'processorsPath' => $corePath.'processors/',
      'controllersPath' => $corePath.'controllers/',
      'templatesPath' => $corePath.'templates/',
      'chunksPath' => $corePath.'elements/chunks/',
      'snippetsPath' => $corePath.'elements/snippets/',

      'baseUrl' => $assetsUrl,
      'cssUrl' => $assetsUrl.'css/',
      'jsUrl' => $assetsUrl.'js/',
      'connectorUrl' => $assetsUrl.'connector.php',

      'app_id' => $this->modx->getOption('facebook_feed.app_id', null, '')
    ),$config);
    $this->app_secret = $this->modx->getOption('facebook_feed.app_secret', null, '');
    $this->access_token = $this->modx->getOption('facebook_feed.access_token', null, '');
  }

  protected function initFB() {
    return new Facebook\Facebook([
      'app_id' => $this->config['app_id'],
      'app_secret' => $this->app_secret,
      'default_graph_version' => 'v2.8',
      'default_access_token' => $this->access_token
    ]);
  }

  public function generateAccessToken() {
    $curl = curl_init();
    $url = 'https://graph.facebook.com/oauth/access_token?client_id='.$this->config['app_id'].'&client_secret='.$this->app_secret.'&grant_type=client_credentials';
    //echo '<pre>'.$url.'</pre>';
    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $url,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0
    ));
    $result = curl_exec($curl);
    if(!$result){
      $xpdo->log(xPDO::LOG_LEVEL_ERROR, 'Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
      return false;
    }
    curl_close($curl);
    $token = substr($result, strpos($result, '=') + 1);

    $setting = $this->modx->getObject('modSystemSetting',array('key' => 'facebook_feed.access_token'));
    if ($setting != null) {
      $setting->set('value',$options['app_id']);
      $setting->save();
      return true;
    }
    return false;
  }

  function calcTimeAgo($time) {
    return $this->getTimeAgo($time);
  }

  /**
   * Gets a properly formatted "time ago" from a specified timestamp. Copied
   * from MODx core output filters.
   *
   * @param string $time
   * @return string
   */
  public function getTimeAgo($time = '') {
    if (empty($time)) return false;
    $this->modx->lexicon->load('filters');
    $agoTS = array();
    $uts = array();
    $uts['start'] = strtotime($time);
    $uts['end'] = time();
    if( $uts['start']!==-1 && $uts['end']!==-1 ) {
      if( $uts['end'] >= $uts['start'] ) {
        $diff = $uts['end'] - $uts['start'];
        $years = intval((floor($diff/31536000)));
        if ($years) $diff = $diff % 31536000;
        $months = intval((floor($diff/2628000)));
        if ($months) $diff = $diff % 2628000;
        $weeks = intval((floor($diff/604800)));
        if ($weeks) $diff = $diff % 604800;
        $days = intval((floor($diff/86400)));
        if ($days) $diff = $diff % 86400;
        $hours = intval((floor($diff/3600)));
        if ($hours) $diff = $diff % 3600;
        $minutes = intval((floor($diff/60)));
        if ($minutes) $diff = $diff % 60;
        $diff = intval($diff);
        $agoTS = array(
          'years' => $years,
          'months' => $months,
          'weeks' => $weeks,
          'days' => $days,
          'hours' => $hours,
          'minutes' => $minutes,
          'seconds' => $diff,
        );
      }
    }
    $ago = array();
    if (!empty($agoTS['years'])) {
      $ago[] = $this->modx->lexicon(($agoTS['years'] > 1 ? 'ago_years' : 'ago_year'),array('time' => $agoTS['years']));
    }
    if (!empty($agoTS['months'])) {
      $ago[] = $this->modx->lexicon(($agoTS['months'] > 1 ? 'ago_months' : 'ago_month'),array('time' => $agoTS['months']));
    }
    if (!empty($agoTS['weeks']) && empty($agoTS['years'])) {
      $ago[] = $this->modx->lexicon(($agoTS['weeks'] > 1 ? 'ago_weeks' : 'ago_week'),array('time' => $agoTS['weeks']));
    }
    if (!empty($agoTS['days']) && empty($agoTS['months']) && empty($agoTS['years'])) {
      $ago[] = $this->modx->lexicon(($agoTS['days'] > 1 ? 'ago_days' : 'ago_day'),array('time' => $agoTS['days']));
    }
    if (!empty($agoTS['hours']) && empty($agoTS['weeks']) && empty($agoTS['months']) && empty($agoTS['years'])) {
      $ago[] = $this->modx->lexicon(($agoTS['hours'] > 1 ? 'ago_hours' : 'ago_hour'),array('time' => $agoTS['hours']));
    }
    if (!empty($agoTS['minutes']) && empty($agoTS['days']) && empty($agoTS['weeks']) && empty($agoTS['months']) && empty($agoTS['years'])) {
      $ago[] = $this->modx->lexicon('ago_minutes',array('time' => $agoTS['minutes']));
    }
    if (empty($ago)) { /* handle <1 min */
      $ago[] = $this->modx->lexicon('ago_seconds',array('time' => $agoTS['seconds']));
    }
    $output = implode(', ',$ago);
    $output = $this->modx->lexicon('ago',array('time' => $output));
    return $output;
  }

  function humanNumber($number) {
    if($number > 999999){
      return number_format((float)$number / 1000000., 1, '.', ''). 'm';
    } elseif ($number > 999) {
      return floor($number/1000) . 'k';
    }
    return (int)$number;
  }

  function runFeed($scriptProperties) {
    $output = '';
    $config = array_merge(array(
      'page' => '1',
      'limit' => 30,
      'tpl' => 'facebook_feed_tpl'
    ), $scriptProperties);

    $fb = $this->initFB();
    $response = $fb->get('/' . $config['page'] . '/feed?fields=full_picture,created_time,id,message,name,description,story,likes.limit(2).summary(true),shares,comments_mirroring_domain,comments,link&summary=true&limit=' . $config['limit']);
    $data = $response->getDecodedBody()['data'];
    foreach ($data as $post) {
      $pinfo = array();
      $pinfo['img'] = $post['full_picture'];
      $pinfo['name'] = $post['name'];
      $pinfo['link'] = $post['link'];
      $pinfo['time_ago'] = $this->calcTimeAgo($post['created_time']);
      $pinfo['likes'] = $this->humanNumber($post['likes']['summary']['total_count']);
      $pinfo['shares'] = $this->humanNumber($post['shares']['count']);
      if(isset($post['message'])){
        $pinfo['message'] = nl2br($post['message']);
      } else if(isset($post['description'])) {
        $pinfo['message'] = nl2br($post['description']);
      } else {
        //ignore other types of posts
        continue;
      }
      $output .= $this->modx->getChunk($config['tpl'], $pinfo);
    }
    //$output .= print_r($data,true);
    return $output;
  }
}
