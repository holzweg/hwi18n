<?php
/**
 * HwInlineTranslate
 *
 * Copyright (c) 2011 holzweg e-commerce solutions - http://www.holzweg.com
 * All rights reserved.
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category   HwInlineTranslate
 * @package    HwInlineTranslate
 * @author     Mathias Geat <mathias.geat@holzweg.com>
 * @copyright  2011 holzweg e-commerce solutions - http://www.holzweg.com
 * @license    http://www.gnu.org/licenses/gpl.txt  GNU GPL v3
 */

/**
 * HwTranslationBackend
 *
 * AJAX backend
 *
 * @category   HwInlineTranslate
 * @package    HwInlineTranslate
 * @author     Mathias Geat <mathias.geat@holzweg.com>
 * @copyright  2011 holzweg e-commerce solutions - http://www.holzweg.com
 * @license    http://www.gnu.org/licenses/gpl.txt  GNU GPL v3
 */
class HwTranslationSecurity
{
    /**
     * INI object
     *
     * @var eZINI
     */
    protected static $_ini;

    /**
     * Check if current user is allowed to use translation
     *
     * @param  eZINI $ini
     * @return boolean
     * @throws HwTranslationSecurityException
     */
    public static function isUserAllowed($user = null, $ini = null)
    {
        if(!$ini instanceof eZINI) {
            $ini = self::getIni();
        }

        $userAllowed = false;

        if($user === null) {
            $user = eZUser::currentUser();
        } elseif(!($user instanceof eZUser)) {
            $user = eZUser::fetch($user);
        }

        if(!($user instanceof eZUser)) {
            throw new HwTranslationSecurityException('Invalid user');
        }

        if(in_array($user->id(), $ini->variable('Access', 'AllowedUsers'))) {
            $userAllowed = true;
        } else {
            foreach($user->roles() as $role) {
                if(in_array($role->ID, $ini->variable('Access', 'AllowedRoles'))) {
                    $userAllowed = true;
                    break;
                }
            }
        }

        return $userAllowed;
    }

    /**
     * Get eZINI instance
     *
     * @return eZINI
     */
    protected static function getIni()
    {
        if(self::$_ini === null) {
            self::$_ini = eZINI::instance('hwi18n.ini');
        }

        return self::$_ini;
    }
}

/**
 * HwTranslationSecurity
 *
 * Exception used by HwTranslationSecurity
 *
 * @category   HwInlineTranslate
 * @package    HwInlineTranslate
 * @author     Mathias Geat <mathias.geat@holzweg.com>
 * @copyright  2011 holzweg e-commerce solutions - http://www.holzweg.com
 * @license    http://www.gnu.org/licenses/gpl.txt  GNU GPL v3
 */
class HwTranslationSecurityException extends Exception {}