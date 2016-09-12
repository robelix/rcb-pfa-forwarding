<?php

/**
 * Change Postfixadmin Forwarding
 *
 * Plugin that gives access to Forwarding using Postfixadmin database
 *
 * @version 1.0 - 10.09.2009
 * @author Pawel Muszynski
 * @licence GNU GPL
 *
 * Requirements: Postfixadmin
 *
 **/

/** USAGE
 *
 * #1- Configure "pfadmin_forwarding/config/config.inc.php".
 * #2- Register plugin ("./config/main.inc.php ::: $rcmail_config['plugins']").
 *
 **/

require_once('plugins/pfadmin_forwarding/pfadmin_functions.php');

class pfadmin_forwarding extends rcube_plugin
{
  public $task = 'settings';
  public $EMAIL_ADDRESS_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[a-z0-9]([a-z0-9\-][.]?)*[a-z0-9]\.[a-z]{2,15})';
  private $sql_select = 'SELECT * FROM alias WHERE address = %u LIMIT 1;';
  private $sql_update = 'UPDATE alias SET goto = %a  WHERE address = %u LIMIT 1;';

  function init()
  {
    $rcmail = rcmail::get_instance();
    $this->_load_config();
    
    if ($rcmail->task == 'settings') {
        $this->add_texts('localization/', true);
        $this->include_stylesheet('skins/larry/style.css');
        $this->add_hook('settings_actions', array($this, 'settings_actions'));
        $this->register_action('plugin.pfadmin_forwarding-save', array($this, 'pfadmin_forwarding_save'));
        $this->register_action('plugin.pfadmin_forwarding', array($this, 'pfadmin_forwarding_init'));
    }
    
    //$this->register_action('plugin.pfadmin_forwarding', array($this, 'pfadmin_forwarding_init'));
    //$this->register_action('plugin.pfadmin_forwarding-save', array($this, 'pfadmin_forwarding_save'));
    //$this->register_handler('plugin.pfadmin_forwarding_form', array($this, 'pfadmin_forwarding_form'));
    //$this->include_script('pfadmin_forwarding.js');
  }

    function settings_actions($args)
    {
        // register as settings action
        $args['actions'][] = array(
            'action' => 'plugin.pfadmin_forwarding',
            'class'  => 'forwarding',
            'label'  => 'pfadmin_forwarding.forwarding',
            'title'  => 'pfadmin_forwarding.forwarding',
            'domain' => 'forwarding',
        );

        return $args;
    }

  function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config = "plugins/pfadmin_forwarding/config/config.inc.php";
    if(file_exists($config))
      include $config;
    if(is_array($rcmail_config)){
      $arr = array_merge($rcmail->config->all(),$rcmail_config);
      $rcmail->config->merge($arr);
    }
  }

  function pfadmin_forwarding_init()
  {
    $this->register_handler('plugin.body', array($this,'pfadmin_forwarding_form'));
    $rcmail = rcmail::get_instance();
    $rcmail->output->set_pagetitle($this->gettext('forwarding'));
    $rcmail->output->send('plugin');
  }

  # to keep current set autoreply
  function add_autoreply($address_a) {
    $settings = $this->_get();
    $address = explode(',',$settings['goto']);
    foreach($address as $a) {
    	if (preg_match('/^.+@.+@autoreply.xyz$/', $a)) {
    		$address_a[] = $a;
    	}
    }
    return $address_a;
  }

  function pfadmin_forwarding_save()
  {

    $rcmail = rcmail::get_instance();
    $user = strtolower($rcmail->user->data['username']);

    $keepcopies   = get_input_value('_keepcopies', RCUBE_INPUT_POST);
    if(!$keepcopies)
      $keepcopies = 0;
    $address      = strtolower(get_input_value('_forwardingaddress', RCUBE_INPUT_POST));
    $order   = array("\r\n", "\n", "\r");
    $address = str_replace($order,",", $address);
    $address_a = array();
    foreach(explode(",",$address) as $a) {
    	if (trim($a)) {
            if (preg_match($this->EMAIL_ADDRESS_PATTERN, $a)) {
                $address_a[] = $a;
            } else {
                $rcmail->output->command('display_message', $this->gettext('invalidaddress').': '.$a , 'error' );
                return $this->pfadmin_forwarding_init();
            }
    	}
    }
    $address_a = array_unique($address_a);
    

	# ganz leer verhindern - in dem Fall in eigene box.
	if (!$address_a) {
		$keepcopies = 1;
	}

    $address_a = $this->add_autoreply($address_a);
    if ($keepcopies) {
      $address_a[] = $user;
    }

    $this->add_texts('localization/');
    
    
    if (!($res = $this->_save($user,$keepcopies,$address_a))) {
      if(isset($_SESSION['dnsblacklisted']) && $_SESSION['dnsblacklisted'] != 'pass'){
        $this->add_texts('../dnsbl/localization/');
        $rcmail->output->command('display_message',sprintf(rcube_label('dnsblacklisted', 'pfadmin_forwarding'),$_SESSION['clientip']),'error');
      }
      else{
          $rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
        }
      }

      if (!$rcmail->config->get('db_persistent')) {
        if ($dsn = $rcmail->config->get('db_dsnw')) {
          $rcmail->db = rcube_db::factory($dsn, '', FALSE);
        }
      }
    $this->pfadmin_forwarding_init();
  }

  function pfadmin_forwarding_form()
  {
    $rcmail = rcmail::get_instance();

    // add some labels to client
    $rcmail->output->add_label(
      'pfadmin_forwarding.forwarding',
      'pfadmin_forwarding.invalidaddress',
      'pfadmin_forwarding.forwardingloop'
    );

    $settings = $this->_get();
    $address     = str_replace(',',"\n",$settings['goto']);
    $address_a = array();
    $address_a = explode("\n", $address);
    $keepcopies  = in_array($rcmail->user->data['username'], $address_a);

    if ($keepcopies) {
      $address_a = array_diff($address_a, array($rcmail->user->data['username']));
    }

    # remove autoresponder addresses
    $cleaned_addr = Array();
    foreach($address_a as $a) {
    	if (!preg_match('/@autoreply\.xyz$/', $a)) {
    		$cleaned_addr[] = $a;
    	}
    }
    $address_a = $cleaned_addr;

    $address = implode("\n", $address_a);

    $rcmail->output->set_env('product_name', $rcmail->config->get('product_name'));

    $table = new html_table(array('cols' => 2));

    // allow the following attributes to be added to the <table> tag
    $attrib_str = html::attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

    // show autoresponder properties

    $field_id = 'forwardingaddress';
    
    $input_forwardingaddress = new html_textarea(array('name' => '_forwardingaddress', 'id' => $field_id, 'value' => $address, 'cols' => 60, 'rows' => 5));
    
    $table->add('title', $this->gettext('forwardingaddress'));
    $table->add(null, $input_forwardingaddress->show());

    $field_id = 'keepcopies';
    $input_keepcopies = new html_checkbox(array('name' => '_keepcopies', 'id' => $field_id, 'value' => 1));

    $table->add('title', $this->gettext('keepcopies'));
    $table->add(null, $input_keepcopies->show($keepcopies?1:0));

    $submit_button = $rcmail->output->button(array(
            'command' => 'plugin.pfadmin_forwarding-save',
            'type'    => 'input',
            'class'   => 'button mainaction',
            'label'   => 'save',
    ));

    $out = html::div(array('class' => 'box'),
        html::div(array('id' => 'prefs-title', 'class' => 'boxtitle'), $this->gettext('forwarding'))
        . html::div(array('class' => 'boxcontent'),
            $table->show(). html::p(null, $submit_button)));

    $rcmail->output->add_gui_object('forwardform', 'pfadmin_forwardingform');

    $this->include_script('pfadmin_forwarding.js');

    return $rcmail->output->form_tag(array(
        'id'     => 'forwardingform',
        'name'   => 'forwardingform',
        'method' => 'post',
        'action' => './?_task=settings&_action=plugin.pfadmin_forwarding-save',
    ), $out);

  }

  private function _get()
  {
    $rcmail = rcmail::get_instance();

    $sql = $this->sql_select;

    if ($dsn = $rcmail->config->get('db_pfadmin_forwarding_dsn')) {
      $db = rcube_db::factory($dsn, '', FALSE);
      $db->set_debug((bool)$rcmail->config->get('sql_debug'));
      $db->db_connect('r');
    } else {
      die("FATAL ERROR ::: RoundCube Plugin ::: pfadmin_forwarding ::: \$rcmail_config['db_pfadmin_forwarding_dsn'] undefined !!! ==> die");
    }
    if ($err = $db->is_error())
      return $err;

    $sql = str_replace('%u', $db->quote($rcmail->user->data['username'],'text'), $sql);
    $res = $db->query($sql);
    if ($err = $db->is_error()){
       return $err;
    }
    $ret = $db->fetch_assoc($res);
    if (!$rcmail->config->get('db_persistent')) {
      if ($dsn = $rcmail->config->get('db_dsnw')) {
        $rcmail->db = rcube_db::factory($dsn, '', FALSE);
      }
    }
    return $ret;
  }

  private function _save($user,$keepcopies,$address)
  {
    $cfg = rcmail::get_instance()->config;

    if ($dsn = $cfg->get('db_pfadmin_forwarding_dsn')) {
      $db = rcube_db::factory($dsn, '', FALSE);
      $db->set_debug((bool)$cfg->get('sql_debug'));
      $db->db_connect('w');
    } else {
      die("FATAL ERROR ::: RoundCube Plugin ::: pfadmin_forwarding ::: \$rcmail_config['db_pfadmin_forwarding_dsn'] undefined !!! ==> die");
    }
    if ($err = $db->is_error())
      return $err;
    $sql = $this->sql_update;

    $aliasy =  removeempty2(implode(",",$address));
    $sql = str_replace('%a',  $db->quote($aliasy,'text'), $sql);
    $sql = str_replace('%k',  $db->quote($keepcopies,'text'), $sql);
    $sql = str_replace('%u',  $db->quote($user,'text'), $sql);

    $res = $db->query($sql);

    if ($err = $db->is_error())
      return $err;
    $res = $db->affected_rows($res);
    if ($res == 0) return $this->gettext('errorsaving');
    if ($res == 1) return FALSE; // THis is the good case - 1 row updated
    return $this->gettext('internalerror');

  }
}

?>
