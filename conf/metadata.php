<?php

$meta['smtp_host'] = array('string');
$meta['smtp_port'] = array('numeric');
$meta['smtp_ssl']  = array('multichoice','_choices' => array('','ssl','tls'));
$meta['smtp_allow_insecure'] = array('onoff');

$meta['localdomain'] = array('string');

$meta['auth_type'] = array('multichoice','_choices' => array('login','oauth'));

$meta['auth_user'] = array('string');
$meta['auth_pass'] = array('password');

$meta['oauth_provider']      = array('multichoice','_choices' => array('google','microsoft','custom'));
$meta['oauth_client_id']     = array('string');
$meta['oauth_client_secret'] = array('password');
$meta['oauth_authurl']       = array('string');
$meta['oauth_tokenurl']      = array('string');
$meta['oauth_scope']         = array('string');

$meta['debug']     = array('onoff');
