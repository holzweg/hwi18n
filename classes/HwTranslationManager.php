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
 * HwTranslationManager
 *
 * Handles reading, parsing and writing of eZPublish *.ts translation files.
 *
 * @category   HwInlineTranslate
 * @package    HwInlineTranslate
 * @author     Mathias Geat <mathias.geat@holzweg.com>
 * @copyright  2011 holzweg e-commerce solutions - http://www.holzweg.com
 * @license    http://www.gnu.org/licenses/gpl.txt  GNU GPL v3
 */
class HwTranslationManager
{
    /**
     * Path to translation file
     *
     * @var string
     */
    protected $_path;

    /**
     * DOMDocument representation of translation file
     *
     * @var DOMDocument
     */
    protected $_dom;

    /**
     * DOMXPath object
     *
     * @var DOMXPath
     */
    protected $_xpath;

    /**
     * Last error caught by error handler
     *
     * @var string
     */
    protected $_catchedError;

    /**
     * Path to RELAX NG schema
     *
     * @var string
     */
    protected $_schemaPath = 'schemas/translation/ts.rng';

    /**
     * Constructor
     *
     * @param  string $path Path to translation file
     */
    public function __construct($path)
    {
        $this->_setPath($path);
    }

    /**
     * Get path to RELAX NG schema
     *
     * @return string
     */
    public function getSchemaPath()
    {
        return $this->_schemaPath;
    }

    /**
     * Set path to RELAX NG schema
     *
     * @param  string $path
     * @return void
     */
    public function setSchemaPath($path)
    {
        $this->_schemaPath = $path;
    }

    /**
     * Get path to translation file
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Set path to translation file
     *
     * @param  string $path
     * @return void
     * @throws HwTranslationManagerException if path does not end in .ts
     */
    protected function _setPath($path)
    {
        if(substr($path, strlen($path) - 3) !== '.ts') {
            throw new HwTranslationManagerException('Path must end in .ts, ' . $path . ' given');
        }

        $this->_path = $path;
    }


    /**
     * Count contexts in current document
     *
     * @return int
     */
    public function getContextCount()
    {
        /* @var $contexts DOMNodeList */
        $contexts = $this->getXPath()->query('context', $this->getTS());
        return $contexts->length;
    }

    /**
     * Check if context exists
     *
     * @param  string $contextName Name of the context
     * @return boolean
     */
    public function hasContext($contextName)
    {
        return ($this->getContext($contextName) !== false);
    }

    /**
     * Get a context element based on context name. Optionally, create the
     * element on-the-fly
     *
     * @param  string $contextName Name of the context
     * @param  bool $create Create the context if it doesn't exist?
     * @return false|DOMElement
     * @throws HwTranslationManagerException if multiple contexts with the same name are found
     */
    public function getContext($contextName, $create = false)
    {
        $contexts = $this->getXPath()->query('context[name="' . $contextName . '"]', $this->getTS());
        if($contexts->length == 1) {
            return $contexts->item(0);
        } elseif($contexts->length > 1) {
            throw new HwTranslationManagerException('Multiple contexts found for ' . $contextName);
        } else {
            if($create) {
                $context = $this->createContext($contextName);
                return $context;
            } else {
                return false;
            }
        }
    }

    /**
     * Create a context element
     *
     * @param  string $contextName Name of the context
     * @return DOMElement
     */
    public function createContext($contextName)
    {
        if(!$this->hasContext($contextName)) {
            $context = $this->getDOM()->createElement('context');
            $name = $this->getDOM()->createElement('name', $contextName);

            $context->appendChild($name);
            $this->getTS()->appendChild($context);

            return $context;
        } else {
            return $this->getContext($contextName);
        }
    }

    /**
     * Remove a context element
     *
     * @param  DOMElement|string $context Context element or name of the context
     * @return boolean
     */
    public function removeContext($context)
    {
        if(!$context instanceof DOMElement) {
            $contextName = (string) $context;
            $context = $this->getContext($contextName);
        }

        if($context !== false) {
            $this->getTS()->removeChild($context);
            return true;
        }

        return false;
    }

    /**
     * Count messages in current document/context
     *
     * @return int
     */
    public function getMessageCount($context = null)
    {
        if($context !== null) {
            if(!$context instanceof DOMElement) {
                $contextName = (string) $context;
                $context = $this->getContext($contextName);
            }

            if($context === false) {
                return 0;
            } else {
                /* @var $messages DOMNodeList */
                $messages = $this->getXPath()->query('message', $context);
                return $messages->length;
            }
        } else {
            /* @var $messages DOMNodeList */
            $messages = $this->getXPath()->query('//message', $this->getTS());
            return $messages->length;
        }
    }

    /**
     * Check if message exists inside a context
     *
     * @param  string $contextName Name of the context
     * @param  string $messageSource Message source
     * @return boolean
     */
    public function hasMessage($contextName, $messageSource)
    {
        return ($this->getMessage($contextName, $messageSource) !== false);
    }

    /**
     * Get a message based on context name and message source
     *
     * @param  string $contextName Name of the context
     * @param  string $messageSource Message source
     * @return false|DOMElement
     * @throws HwTranslationManagerException if multiple messages with the same source are found
     */
    public function getMessage($contextName, $messageSource)
    {
        $context = $this->getContext($contextName);
        if($context === false) {
            return false;
        } else {
            $messages = $this->getXPath()->query('message[source="' . $messageSource . '"]', $context);
            if($messages->length == 1) {
                return $messages->item(0);
            } elseif($messages->length > 1) {
                throw new HwTranslationManagerException('Multiple messages found for ' . $messageSource);
            } else {
                return false;
            }
        }
    }

    /**
     * Create a message. If the message context/source already exists, the existing
     * message will be overwritten.
     *
     * @param  string $contextName Name of the context, will be created on-the-fly if it doesn't exist
     * @param  string $messageSource Message source
     * @param  string $translation Translation
     * @return DOMElement
     */
    public function createMessage($contextName, $messageSource, $translation)
    {
        if(!$this->hasMessage($contextName, $messageSource)) {
            $context = $this->getContext($contextName, true);

            $message = $this->getDOM()->createElement('message');
            $source = $this->getDOM()->createElement('source', $messageSource);
            $translation = $this->getDOM()->createElement('translation', $translation);

            $message->appendChild($source);
            $message->appendChild($translation);
            $context->appendChild($message);
            return $message;
        } else {
            return $this->setTranslation($contextName, $messageSource, $translation);
        }
    }

    /**
     * Remove a message either by passing $contextName/$messageSource or
     * a DOMElement as $message parameter
     *
     * @param  string $contextName Name of the context (set to null if you pass $message)
     * @param  string $messageSource Message source (set to null if you pass $message)
     * @param  bool $cleanup Remove message context if the context is empty after removing the message?
     * @param  DOMElement $message Message element to use instead of context/source strings
     * @return boolean
     */
    public function removeMessage($contextName, $messageSource, $cleanup = true, $message = null)
    {
        if(!($message !== null && $message instanceof DOMElement)) {
            $message = $this->getMessage($contextName, $messageSource);
        }

        if($message !== false) {
            $context = $message->parentNode;
            $context->removeChild($message);

            if($cleanup) {
                $messages = $this->getXPath()->query('message', $context);
                if($messages->length == 0) {
                    $this->removeContext($context);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Get translation for context/source combination
     *
     * @param  string $contextName Name of the context
     * @param  string $messageSource Message source
     * @return string|false
     */
    public function getTranslation($contextName, $messageSource)
    {
        $message = $this->getMessage($contextName, $messageSource);
        if($message !== false) {
            return $this->getXPath()->query('translation', $message)->item(0)->nodeValue;
        }

        return false;
    }

    /**
     * Set translation for context/source combination
     *
     * @param  string $contextName Name of the context
     * @param  string $messageSource Message source
     * @param  string $translation Translation to set
     * @return DOMElement Edited message element
     */
    public function setTranslation($contextName, $messageSource, $translation)
    {
        $message = $this->getMessage($contextName, $messageSource);
        if($message === false) {
            $message = $this->createMessage($contextName, $messageSource, $translation);
        } else {
            $this->getXPath()->query('translation', $message)->item(0)->nodeValue = $translation;
        }

        return $message;
    }

    /**
     * Get TS root element
     *
     * @return DOMElement
     */
    public function getTS()
    {
        return $this->getXPath()->query('/TS')->item(0);
    }

    /**
     * Get DOMXPath object
     *
     * @return DOMXPath
     */
    public function getXPath()
    {
        if($this->_xpath === null) {
            $this->_xpath = new DOMXPath($this->getDOM());
        }

        return $this->_xpath;
    }

    /**
     * Get DOMDocument. Lazy-loading will handle loading of the document on first usage.
     *
     * @return DOMDocument
     * @throws HwTranslationManagerException if loading goes wrong or document is not valid
     */
    public function getDOM()
    {
        if($this->_dom === null) {
            $dom = new DOMDocument();

            $this->setErrorHandler();
            $dom->load($this->getPath());
            $this->restoreErrorHandler();

            if($dom === false) {
                throw new HwTranslationManagerException($this->getCatchedError());
            }

            if(!$this->validate($dom)) {
                throw new HwTranslationManagerException('Validation error (load): ' . $this->getCatchedError());
            }

            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;

            $this->_dom = $dom;
        }

        return $this->_dom;
    }

    /**
     * Validate DOMDocument with eZPublish RELAX NG schema
     *
     * @param  DOMDocument $dom
     * @return boolean
     */
    public function validate(DOMDocument $dom = null)
    {
        $schemaPath = $this->getSchemaPath();
        if(!file_exists($schemaPath)) {
            throw new HwTranslationManagerException('Validation error: RELAX NG schema not found');
        }

        if($dom === null) {
            $dom = $this->getDOM();
        }

        $this->setErrorHandler();
        $validation = $dom->RelaxNGValidate($schemaPath);
        $this->restoreErrorHandler();

        if(!$validation) {
            $message = $this->getCatchedError();
            if(strpos($message, 'Expecting an element , got nothing') !== false) {
                $validation = true;
            } else {
                $this->_catchedError = $message;
            }
        }

        return $validation;
    }

    /**
     * Save DOMDocument to XML file
     *
     * @param  string $path Path to save the document to, will use the path used for loading if set to null
     * @param  boolean $validate Validate the document before saving
     * @return boolean
     * @throws HwTranslationManagerException if validation or saving goes wrong
     */
    public function save($path = null, $validate = true)
    {
        if($path === null) {
            $path = $this->getPath();
        }

        if($validate && !$this->validate()) {
            throw new HwTranslationManagerException('Validation error (save): ' . $this->getCatchedError());
        }

        $this->setErrorHandler();
        $saveReturn = $this->getDOM()->save($path);
        $this->restoreErrorHandler();

        if($saveReturn === false) {
            throw new HwTranslationManagerException($this->getCatchedError());
        }

        return ($saveReturn !== false) ? true : false;
    }


    /**
     * Check if translation file is readable
     *
     * @return boolean
     */
    public function translationFileIsReadable()
    {
        $this->setErrorHandler();
        $result = is_readable($this->getPath());
        $this->restoreErrorHandler();

        return $result;
    }

    /**
     * Check if translation file is writable
     *
     * @return boolean
     */
    public function translationFileIsWritable()
    {
        $this->setErrorHandler();
        $result = is_writable($this->getPath());
        $this->restoreErrorHandler();

        return $result;
    }

    /**
     * Check if translation file exists
     *
     * @return boolean
     */
    public function translationFileExists()
    {
        return (file_exists($this->getPath()) && is_file($this->getPath()));
    }

    /**
     * Create a new translation file in defined path
     *
     * @param type $language
     * @param type $sourceLanguage
     */
    public function createFile($language = 'en_US', $sourceLanguage = 'en')
    {
        if(!$this->translationFileExists()) {
            $path = $this->getPath();
            $dirName = dirname($path);
            if(!file_exists($dirName) || !is_dir($dirName)) {
                $this->setErrorHandler();
                $mkdirReturn = mkdir($dirName, null, true);
                $this->restoreErrorHandler();

                if($mkdirReturn === false) {
                    $error = $this->getCatchedError();
                    if(!emtpy($error)) {
                        $error = '. ' . $error;
                    } else {
                        $error = '';
                    }

                    throw new HwTranslationManagerException('Failed to create directory' . $error);
                }
            }

            $xml = <<<HDOC
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.0" language="{$language}" sourcelanguage="{$sourceLanguage}" />
HDOC;

            $this->setErrorHandler();
            $putReturn = file_put_contents($path, $xml);
            $this->restoreErrorHandler();

            if($putReturn === false) {
                $error = $this->getCatchedError();
                if(!emtpy($error)) {
                    $error = '. ' . $error;
                } else {
                    $error = '';
                }

                throw new HwTranslationManagerException('Failed to create file' . $error);
            }
        }
    }

    /**
     * Error handler used to catch PHP warnings and errors
     *
     * @param  int $errno
     * @param  string $errstr
     * @param  string $errfile
     * @param  int $errline
     * @param  array $errcontext
     * @return void
     */
    public function catchErrorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $this->_catchedError = $errstr;
    }

    /**
     * Get the last catched error
     *
     * @return string
     */
    protected function getCatchedError()
    {
        $error = $this->_catchedError;
        $this->_catchedError = null;

        return $error;
    }

    /**
     * Set the PHP error handler to the internal catchErrorHandler
     *
     * @return void
     */
    protected function setErrorHandler()
    {
        set_error_handler(array($this, 'catchErrorHandler'));
    }

    /**
     * Restore PHP's default error handler
     *
     * @return void
     */
    protected function restoreErrorHandler()
    {
        restore_error_handler();
    }
}

/**
 * HwTranslationManagerException
 *
 * Exception used by HwTranslationManager
 *
 * @category   HwInlineTranslate
 * @package    HwInlineTranslate
 * @author     Mathias Geat <mathias.geat@holzweg.com>
 * @copyright  2011 holzweg e-commerce solutions - http://www.holzweg.com
 * @license    http://www.gnu.org/licenses/gpl.txt  GNU GPL v3
 */
class HwTranslationManagerException extends Exception {}