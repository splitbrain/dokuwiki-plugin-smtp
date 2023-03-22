<?php

$meta['smtp_host'] = array('string');
$meta['smtp_port'] = array('numeric');
$meta['smtp_ssl']  = array('multichoice','_choices' => array('','ssl','tls'));
$meta['smtp_ssl_allow_insecure'] = array('onoff');

$meta['auth_user'] = array('string');
$meta['auth_pass'] = array('password');

$meta['localdomain'] = array('string');

$meta['debug']     = array('onoff');

