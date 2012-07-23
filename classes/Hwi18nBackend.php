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
 * HwTranslationSecurity
 */
require_once('HwTranslationSecurity.php');

/**
 * HwTranslationManager
 */
require_once('HwTranslationManager.php');

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
class Hwi18nBackend extends ezjscServerFunctionsJs
{
    /**
     * INI object
     *
     * @var eZINI
     */
    protected static $_ini;

    /**
     * Translation manager
     *
     * @var HwTranslationManager
     */
    protected static $_translationManager = array();

    /**
     * Handle the translate request from frontend for locale/context/source combination
     * Returns JSON
     *
     * @return void
     */
    public static function translate()
    {
        $validRequest = true;

        if(!hwTranslationSecurity::isUserAllowed()) {
            $validRequest = false;
            $errorStatus = 403;
            $errorMsg = 'Forbidden';
        }

        $locale = trim($_REQUEST['locale']);
        $context = trim($_REQUEST['context']);
        $source = $_REQUEST['source'];
        $translation = $_REQUEST['translation'];

        if($validRequest) {
            $ret = array(
                'status' => 200,
                'error' => '',
                'locale' => $locale,
                'context' => $context,
                'source' => $source,
                'translation' => $translation
            );
        } else {
            $ret = array();
        }

        if($validRequest) {
            $validRequest = self::_checkValidRequest($locale, $context, $source);

            if(is_array($validRequest)) {
                $errorStatus = $validRequest[0];
                $errorMsg = $validRequest[1];
                $validRequest = false;
            }
        }

        if($validRequest) {
            try {
                $mg = self::_getTranslationManager($locale);
                if(empty($translation)) {
                    $mg->removeMessage($context, $source, true);
                    $ret['translation'] = ezpI18n::tr($context, $source, null, null);
                } else {
                    $mg->setTranslation($context, $source, $translation);
                    $ret['translation'] = $translation;
                }
                $mg->save();
            } catch(Exception $e) {
                $ret['status'] = 500;
                $ret['error'] = $e->getMessage();
            }
        } else {
            $ret['status'] = $errorStatus;
            $ret['error'] = $errorMsg;
        }

        header('Content-type: application/json');
        echo json_encode($ret);
        eZExecution::cleanExit();
    }

    /**
     * Get translation for locale/context/source combination
     * Returns JSON
     *
     * @return void
     */
    public static function getTranslation()
    {
        $validRequest = true;

        if(!hwTranslationSecurity::isUserAllowed()) {
            $validRequest = false;
            $errorStatus = 403;
            $errorMsg = 'Forbidden';
        }

        $locale = trim($_REQUEST['locale']);
        $context = trim($_REQUEST['context']);
        $source = $_REQUEST['source'];

        $ret = array(
            'status' => 200,
            'error' => '',
        );

        if($validRequest) {
            $validRequest = self::_checkValidRequest($locale, $context, $source);
            if(is_array($validRequest)) {
                $errorStatus = $validRequest[0];
                $errorMsg = $validRequest[1];
                $validRequest = false;
            }
        }

        if($validRequest) {
            try {
                $ret['translation'] = ezpI18n::tr($context, $source, null, null, true);
            } catch(Exception $e) {
                $ret['status'] = 500;
                $ret['error'] = $e->getMessage();
            }
        } else {
            $ret['status'] = $errorStatus;
            $ret['error'] = $errorMsg;
        }

        header('Content-type: text/plain');
        echo json_encode($ret);
        eZExecution::cleanExit();
    }

    /**
     * Check if request variables are valid
     * Returns true if valid, array with error info otherwise
     *
     * @param  string $locale
     * @param  string $context
     * @param  string $source
     * @return array|true
     */
    protected static function _checkValidRequest($locale, $context, $source)
    {
        $validRequest = true;

        if(!preg_match('/([a-z]{3})\-([A-Z]{2})/', $locale)) {
            $validRequest = false;
            $errorStatus = 500;
            $errorMsg = 'Invalid input parameters: invalid locale';
        }

        if(empty($context)) {
            $validRequest = false;
            $errorStatus = 500;
            $errorMsg = 'Invalid input parameters: context empty';
        }

        if(empty($source)) {
            $validRequest = false;
            $errorStatus = 500;
            $errorMsg = 'Invalid input parameters: source empty';
        }

        if(!$validRequest) {
            return array($errorStatus, $errorMsg);
        } else {
            return true;
        }
    }

    /**
     * Get a translation manager instance
     *
     * @param  string $locale
     * @return HwTranslationManager|false
     */
    protected static function _getTranslationManager($locale)
    {
        if(!isset(self::$_translationManager[$locale])) {
            $path = self::_getTranslationFilePath($locale);

            if(!file_exists($path)) {

                if(!file_exists(self::_getTranslationsPath())) {
                    return false;
                }

                $localeDirPath = self::_getTranslationsPath() . "/" . $locale;
                if(!file_exists($localeDirPath)) {
                    mkdir($localeDirPath);
                }

                if(!file_exists($localeDirPath)) {
                    return false;
                }

                // initialize xml file
                $ezlocale = new eZLocale($locale);
                $localeCode = $ezlocale->HTTPLocaleCode();
                $localeCode = str_replace("-", "_", $localeCode);
                $contents = '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE TS>
                    <TS version="2.0" language="'.$localeCode.'" sourcelanguage="en"></TS>';
                file_put_contents($path, $contents);
            }

            self::$_translationManager[$locale] = new HwTranslationManager($path);
        }

        return self::$_translationManager[$locale];
    }

    /**
     * Get path to translation file
     *
     * @param  string $locale
     * @return string
     */
    protected static function _getTranslationFilePath($locale)
    {
        return self::_getTranslationsPath() . '/' . $locale . '/translation.ts';
    }

    /**
     * Get path to translation files based on ini settings
     *
     * @return string
     */
    protected static function _getTranslationsPath()
    {
        $path = realpath(dirname(__FILE__) . '/../../../');
        $path .= '/' . self::_getIniSetting('Translations', 'TranslationsPath');

        return $path;
    }

    /**
     * Read setting from ini file
     *
     * @param  string$blockName
     * @param  string $varName
     * @return string
     */
    protected static function _getIniSetting($blockName, $varName)
    {
        return self::_getIni()->variable($blockName, $varName);
    }

    /**
     * Get eZINI instance
     *
     * @return eZINI
     */
    protected static function _getIni()
    {
        if(self::$_ini === null) {
            self::$_ini = eZINI::instance('hwi18n.ini');
        }

        return self::$_ini;
    }
}