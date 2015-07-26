<?php
/**
 * DokuWiki Plugin smtp (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class admin_plugin_smtp extends DokuWiki_Admin_Plugin {

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort() {
        return 500;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        ptln('<h1>'.$this->getLang('menu').'</h1>');


        require_once __DIR__.'/Mailer/Mailer/SMTP.php';
        $mailer = new Tx\Mailer\SMTP();
        $mailer->setServer()

    }
}

// vim:ts=4:sw=4:et:
