<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki Plugin smtp (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */
class helper_plugin_smtp extends Plugin
{
    /**
     * Return a string usable as EHLO message
     *
     * @param string $ehlo configured EHLO (overrides automatic detection)
     * @return string
     */
    public static function getEHLO($ehlo = '')
    {
        if (empty($ehlo)) {
            $ip = $_SERVER["SERVER_ADDR"] ?? '';
            if (empty($ip)) {
                return "localhost.localdomain";
            }

            // Indicate IPv6 address according to RFC 2821, if applicable.
            $colonPos = strpos($ip, ':');
            if ($colonPos !== false) {
                $ip = 'IPv6:' . $ip;
            }

            return "[" . $ip . "]";
        }
        return $ehlo;
    }
}
