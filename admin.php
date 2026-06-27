<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;

/**
 * Swiftmail Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

class admin_plugin_smtp extends AdminPlugin
{
    /**
     * return sort order for position in admin menu
     */
    public function getMenuSort()
    {
        return 200;
    }

    /**
     * handle user request
     */
    public function handle()
    {
        global $INPUT;
        global $conf;
        if (!$INPUT->bool('send')) return;

        // make sure debugging is on;
        $conf['plugin']['smtp']['debug'] = 1;

        // send a mail
        $mail = new Mailer();
        if ($INPUT->str('to')) $mail->to($INPUT->str('to'));
        if ($INPUT->str('cc')) $mail->cc($INPUT->str('cc'));
        if ($INPUT->str('bcc')) $mail->bcc($INPUT->str('bcc'));
        $mail->subject('DokuWiki says hello');
        $mail->setBody("Hi @USER@\n\nThis is a test from @DOKUWIKIURL@");

        $ok = $mail->send();

        // check result
        if ($ok) {
            msg('Message was sent. SMTP seems to work.', 1);
        } else {
            msg('Message wasn\'t sent. SMTP seems not to work properly.', -1);
        }
    }

    /**
     * Output HTML form
     */
    public function html()
    {
        global $INPUT;
        global $conf;

        echo $this->locale_xhtml('intro');

        if (!$conf['mailfrom']) msg($this->getLang('nofrom'), -1);


        $form = new Form();
        $form->addClass('plugin_smtp_admin');
        $form->addFieldsetOpen('Testmail');
        $form->setHiddenField('send', 1);
        $form->addTextInput('to', 'To:')->val($INPUT->str('to'));
        $form->addTextInput('cc', 'Cc:')->val($INPUT->str('cc'));
        $form->addTextInput('bcc', 'Bcc:')->val($INPUT->str('bcc'));
        $form->addButton('submit', 'Send Email')->attr('type', 'submit');
        $form->addFieldsetClose();

        echo $form->toHTML();
    }
}
