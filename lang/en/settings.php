<?php

$lang['smtp_host'] = 'Your outgoing SMTP server.';
$lang['smtp_port'] = 'The port your SMTP server listens on. Usually 25. 465 for SSL.';
$lang['smtp_ssl']  = 'What kind of encryption is used when communicating with your SMTP Server?'; // off, ssl, tls

$lang['smtp_ssl_o_']      = 'none';
$lang['smtp_ssl_o_ssl']   = 'SSL';
$lang['smtp_ssl_o_tls']   = 'TLS';

$lang['smtp_allow_insecure'] = 'Accept invalid or self-signed server certificates? Only enable this if you trust the SMTP server despite an untrusted certificate.';

$lang['localdomain'] = 'The name to be used during HELO phase of SMTP. Should be the FQDN of the webserver DokuWiki is running on. Leave empty for autodetection.';

$lang['auth_type'] = 'How to authenticate against the SMTP server. <code>login</code> uses the user name and password below. <code>oauth</code> uses OAuth 2.0 (required by Gmail and Microsoft 365); connect the account in the SMTP admin after configuring the client below.';
$lang['auth_type_o_login'] = 'User name and password (LOGIN)';
$lang['auth_type_o_oauth'] = 'OAuth 2.0 (XOAUTH2)';

$lang['auth_user'] = 'If LOGIN authentication is required, put your user name here.';
$lang['auth_pass'] = 'Password for the above user.';

$lang['oauth_provider'] = 'The OAuth 2.0 provider to use. Pick a preset or <code>custom</code> to configure the endpoints below manually.';
$lang['oauth_provider_o_google']    = 'Google / Gmail';
$lang['oauth_provider_o_microsoft'] = 'Microsoft 365 / Outlook';
$lang['oauth_provider_o_custom']    = 'Custom';

$lang['oauth_client_id']     = 'The OAuth client ID created in the provider\'s developer console.';
$lang['oauth_client_secret'] = 'The OAuth client secret belonging to the client ID above.';
$lang['oauth_authurl']       = 'Custom provider only: the authorization endpoint URL.';
$lang['oauth_tokenurl']      = 'Custom provider only: the token endpoint URL.';
$lang['oauth_scope']         = 'Custom provider only: the scope to request (overrides the preset scope when set).';

$lang['debug'] = 'Print a full error log when sending fails? Disable when everything works!';
