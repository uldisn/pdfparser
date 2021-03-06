<?php

/**
 * @file
 *          This file is part of the PdfParser library.
 *
 * @author  Sébastien MALOT <sebastien@malot.fr>
 * @date    2013-08-08
 * @license GPL-3.0
 * @url     <https://github.com/smalot/pdfparser>
 *
 *  PdfParser is a pdf library written in PHP, extraction oriented.
 *  Copyright (C) 2014 - Sébastien MALOT <sebastien@malot.fr>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.
 *  If not, see <http://www.pdfparser.org/sites/default/LICENSE.txt>.
 *
 */

namespace Smalot\PdfParser;

use Smalot\PdfParser\XObject\Form;
use Smalot\PdfParser\XObject\Image;

/**
 * Class Object
 *
 * @package Smalot\PdfParser
 */
class Object
{
    
    var $collected_text;
    var $actual_line;
    
    const TYPE = 't';

    const OPERATOR = 'o';

    const COMMAND = 'c';

    /**
     * @var Document
     */
    protected $document = null;

    /**
     * @var Header
     */
    protected $header = null;

    /**
     * @var string
     */
    protected $content = null;

    /**
     * @param Document $document
     * @param Header   $header
     * @param string   $content
     */
    public function __construct(Document $document, Header $header = null, $content = null)
    {
        $this->document = $document;
        $this->header   = !is_null($header) ? $header : new Header();
        $this->content  = $content;
    }

    /**
     *
     */
    public function init()
    {

    }

    /**
     * @return null|Header
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @param string $name
     *
     * @return Element|Object
     */
    public function get($name)
    {
        return $this->header->get($name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function has($name)
    {
        return $this->header->has($name);
    }

    /**
     * @param bool $deep
     *
     * @return array
     */
    public function getDetails($deep = true)
    {
        return $this->header->getDetails($deep);
    }

    /**
     * @return null|string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param $content
     */
    public function cleanContent($content, $char = 'X')
    {
        $char    = $char[0];
        $content = str_replace(array('\\\\', '\\)', '\\('), $char . $char, $content);

        // Remove image bloc with binary content
        preg_match_all('/\s(BI\s.*?(\sID\s).*?(\sEI))\s/s', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $part) {
            $content = substr_replace($content, str_repeat($char, strlen($part[0])), $part[1], strlen($part[0]));
        }

        // Clean content in square brackets [.....]
        preg_match_all('/\[((\(.*?\)|[0-9\.\-\s]*)*)\]/s', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $part) {
            $content = substr_replace($content, str_repeat($char, strlen($part[0])), $part[1], strlen($part[0]));
        }

        // Clean content in round brackets (.....)
        preg_match_all('/\((.*?)\)/s', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $part) {
            $content = substr_replace($content, str_repeat($char, strlen($part[0])), $part[1], strlen($part[0]));
        }

        // Clean structure
        if ($parts = preg_split('/(<|>)/s', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)) {
            $content = '';
            $level   = 0;
            foreach ($parts as $part) {
                if ($part == '<') {
                    $level++;
                }

                $content .= ($level == 0 ? $part : str_repeat($char, strlen($part)));

                if ($part == '>') {
                    $level--;
                }
            }
        }

        // Clean BDC and EMC markup
        preg_match_all(
            '/(\/[A-Za-z0-9\_]*\s*' . preg_quote($char) . '*BDC)/s',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        foreach ($matches[1] as $part) {
            $content = substr_replace($content, str_repeat($char, strlen($part[0])), $part[1], strlen($part[0]));
        }

        preg_match_all('/\s(EMC)\s/s', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $part) {
            $content = substr_replace($content, str_repeat($char, strlen($part[0])), $part[1], strlen($part[0]));
        }

        return $content;
    }

    /**
     * @param $content
     *
     * @return array
     */
    public function getSectionsText($content)
    {
        $sections    = array();
        $content     = ' ' . $content . ' ';
        $textCleaned = $this->cleanContent($content, '_');

        // Extract text blocks.
        if (preg_match_all('/\s+BT[\s|\(|\[]+(.*?)\s+ET/s', $textCleaned, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $part) {
                $text    = $part[0];
                $offset  = $part[1];
                $section = substr($content, $offset, strlen($text));

                // Removes BDC and EMC markup.
                $section = preg_replace('/(\/[A-Za-z0-9]+\s*<<.*?)(>>\s*BDC)(.*?)(EMC\s+)/s', '${3}', $section . ' ');

                $sections[] = $section;
            }
        }

        // Extract 'do' commands.
        if (preg_match_all('/(\/[A-Za-z0-9\.\-_]+\s+Do)\s/s', $textCleaned, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $part) {
                $text    = $part[0];
                $offset  = $part[1];
                $section = substr($content, $offset, strlen($text));

                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @param Page
     *
     * @return string
     * @throws \Exception
     */
    public function getText(Page $page = null)
    {
        $bDebug = defined('DEBUG_SMALOT_OBJECT') && DEBUG_SMALOT_OBJECT;
        $collected_text  = FALSE;
        $sections            = $this->getSectionsText($this->content);
        $current_font        = new Font($this->document);
        $current_position_td = array('x' => false, 'y' => false);
        $current_position_tm = array('x' => false, 'y' => false);
        
        //UN
        $prev_font_size = FALSE;
        $font_size = FALSE;
        $y_delta = 0;
        $font_size_delta = 0;

        foreach ($sections as $ks => $section) {
            unset($sections[$ks]);
            $commands = $this->getCommandsText($section);
            unset($section);

            foreach ($commands as $kc => $command) {
                unset($commands[$kc]);
                if($bDebug) echo $command[self::OPERATOR].':';
                switch ($command[self::OPERATOR]) {
                    // set character spacing
                    case 'Tc':
                        if($bDebug) echo 'Command:'.$command[self::COMMAND];
                        break;

                    // move text current point
                    case 'Td':
                        $args = preg_split('/\s/s', $command[self::COMMAND]);
                        $y    = array_pop($args);
                        if($y>0)
                            $y = floor(100*$y)/100;
                        elseif($y<0)
                            $y = ceil(100*$y)/100;
                        $x    = array_pop($args);
                        if($bDebug) echo '/x='.$x.'/y='.$y;
                        
                        if ((floatval($x) <= 0) ||
                            ($current_position_td['y'] !== false && floatval($y) < floatval($current_position_td['y']))
                        ) {
                            // vertical offset
                            //$text .= "\n";
                            if($bDebug) echo '/LF';
                            $current_position_tm['x'] = 0; 
                            $current_position_tm['y'] -= 11;
                            $this->newLine('', $current_position_tm['x'], $current_position_tm['y'],$collected_text);
                        } elseif ($current_position_td['x'] !== false && floatval($x) > floatval(
                                $current_position_td['x']
                            )
                        ) {
                            /**
                             *  horizontal offset
                             * nonjemu, jo atseviskjos gadijumos lv burtiem prieksha lika tukshumu
                             */
                            //$text .= ' ';
                        }
                        $current_position_td = array('x' => $x, 'y' => $y);
                        break;

                    // move text current point and set leading
                    case 'TD':
                        $args = preg_split('/\s/s', $command[self::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if($bDebug) echo '/x='.$x.'/y='.$y;
                        if(empty($collected_text)){
                            if($bDebug) echo '/LF';
                            $this->newLine('',$x, $y,$collected_text);
                            $current_position_tm = array('x' => $x, 'y' => $y);
                            if($bDebug) echo '|:added new empty collect';
                        }                        

                        if (floatval($y) > 0) {
                            $y_delta = $y;                            
                        }
                        if (floatval($y) < 0) {
                            //$text = "\n";
                            $current_position_tm['y'] += $y;
                            $current_position_tm['x'] += $x;
                            if($bDebug) echo '/LF';
                            $this->newLine('', $current_position_tm['x'], $current_position_tm['y'],$collected_text);
                            if($bDebug) echo '/fixY='.$current_position_tm['y'].'|:added new empty collect';
                        } elseif (floatval($x) <= 0) {
                            $this->appendToLine(' ',$collected_text);
                            if($bDebug) echo '|:horizontal offset';
                        }
                        
                        break;

                    case 'Tf':
                        if($bDebug) echo 'command:'.$command[self::COMMAND];
                        $prev_font_size = $font_size;
                        list($id,$font_size) = preg_split('/\s/s', $command[self::COMMAND]);
                        $font_size_delta = $prev_font_size - $font_size;
                        $id           = trim($id, '/');
                        
                        $current_font = $page->getFont($id);
                        if($bDebug) echo '/fontId='.$id.'/size='.$font_size;
                        break;

                    case "'":
                    case 'Tj':
                        //if($bDebug) echo 'command:'.$command;
                        $command[self::COMMAND] = array($command);
                    case 'TJ':
                        // Skip if not previously defined, should never happened.
                        $text = '';
                        if (is_null($current_font)) {
                            // Fallback
                            // TODO : Improve
                            $text .= $command[self::COMMAND][0][self::COMMAND];
                            if($bDebug) echo '/fd:'.$font_size_delta.'/yd:'.$y_delta.'/AddText:'.$text;
                            continue;
                        }


                     
                        $text .= $current_font->decodeText($command[self::COMMAND]);
                        
                        //identifice prim pantu
                        if($font_size_delta > 3 
                                && $font_size_delta < 8 
                                && $y_delta > 4 
                                && $y_delta < 8
                                && trim($text) != ''){
                                $text = '<sup>'.trim($text).'</sup>';                            
                        }

                        //$this->collected_text[$y_actual]['text'] .= $text . $sub_text;
                        $this->appendToLine($text ,$collected_text);
                        if($bDebug) echo '/fsd:'.$font_size_delta.'/yd:'.$y_delta.'/AddText:'.$text;

                        break;

                    // set leading
                    case 'TL':
                        //$text = ' ';
                        if($bDebug) echo 'add space';
                        //$this->collected_text[$y_actual]['text'] .= ' ';
                        $this->appendToLine(' ',$collected_text);
                        break;

                    case 'Tm':
                        
                        $args = preg_split('/\s/s', $command[self::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if($bDebug) echo '/x='.$x.'/y='.$y;
//                        $text = FALSE;
//                        if ($current_position_tm['y'] !== false) {
//                            $delta = abs(floatval($y) - floatval($current_position_tm['y']));
//                            if ($delta > 10) {
//                            $this->collected_text[] = array('text' => '', 'y' => $y);
//                            end($this->collected_text);
//                            $y_actual = key($this->collected_text);
//                                
//                            }
//                        }
                        $y_delta = $y - $current_position_tm['y'];
                        ////                        
                        //identifice prim pantu
                        //if($bDebug) echo '/fsd:'.$font_size_delta.'/pfs:'.$prev_font_size.'/fs:'.$font_size.'/yd:'.$y_delta;
                        if($font_size_delta > 3 && $prev_font_size - $font_size < 8 
                                && $y_delta > 4 && $y_delta < 8){
                                break;                          
                        }                        
                        if($bDebug) echo '/LF';
                        $this->newLine('', $x, $y,$collected_text);
                        //$current_position_tm = array('x' => $x, 'y' => $y);
                        $current_position_tm = array('x' => $x, 'y' => $y);

                        break;

                    // set super/subscripting text rise
                    case 'Ts':
                        break;

                    // set word spacing
                    case 'Tw':
                        break;

                    // set horizontal scaling
                    case 'Tz':
                        //$text = "\n";
                        
                        $current_position_tm['y'] -= 16;//pieliku uz dullo -16
                        if($bDebug) echo '/new_y='.$current_position_tm['y'].'/add new collect';
                        $this->newLine('', 0,$current_position_tm['y'],$collected_text);
                        break;

                    // move to start of next line
                    case 'T*':
                        //$text = "\n";
                        $current_position_tm['y'] -= 16;//pieliku uz dullo -16
                        if($bDebug) echo '/new_y='.$current_position_tm['y'].'/add new collect';
                        $this->newLine('',0, $current_position_tm['y'],$collected_text);
                        break;

                    case 'Da':
                        break;

                    case 'Do':
                        if (!is_null($page)) {
                            $args = preg_split('/\s/s', $command[self::COMMAND]);
                            $id   = trim(array_pop($args), '/ ');
                            if ($xobject = $page->getXObject($id)) {
                                $text = $xobject->getText($page);
                                $this->newLine($text, $current_position_tm['x'],$current_position_tm['y'],$collected_text);
                                if($bDebug) echo $text;
                            }
                        }
                        break;

                    case 'rg':
                    case 'RG':
                        break;

                    case 're':
                        break;

                    case 'co':
                        break;

                    case 'cs':
                        break;

                    case 'gs':
                        break;

                    case 'en':
                        break;

                    case 'sc':
                    case 'SC':
                        break;

                    case 'g':
                    case 'G':
                        break;

                    case 'V':
                        break;

                    case 'vo':
                    case 'Vo':
                        break;

                    default:
                }
                if($bDebug) echo PHP_EOL;
            }
        }
        
        return $this->implodeCollectedText($collected_text);


    }
    
    public function newLine($text,$x, $y,&$collected_text) {
        while (isset($collected_text[strval($y)]))
            $y = floatval($y) - 0.001;

        $collected_text[strval($y)] = array(
            'text' => $text, 
            'x' => $x,
            'y' => $y,
                );
    }

    public function appendToLine($text,&$collected_text) {
        $keys = array_keys($collected_text);
        $last = end($keys);
        
        $collected_text[$last]['text'] .= $text;
    }

    
    /**
     * process collected 
     * @return string
     */
    public function implodeCollectedText(&$collected_text){
        
        
        $bDebug = defined('DEBUG_SMALOT_OBJECT') && DEBUG_SMALOT_OBJECT;
        
        //sort by Y descending
        krsort($collected_text, SORT_NUMERIC);
        
        //init avlues for loop
        $y_max = FALSE;
        $row = array();
        $out_text = '';

        foreach ($collected_text as $y => $xtext) {
            unset($collected_text[$y]);
            $y = floatval($y);
            //echo 'y:'.$y.'/';
            if (!$y_max) {
                /**
                 * init
                 */
                $y_max = $y;
                $row[] = $xtext;
                continue;
            }
            if ($y_max - 10 < $y) {
                /**
                 * same row
                 */
                $row[] = $xtext;
                continue;
            }
            
            /**
             * new row
             */
            //actual row sort by Y
            $krow = array();
            foreach ($row as $xt) {
                $x = $xt['x'];
                if( isset($krow[$x])){
                    $x ++;
                }                
                $krow[$x] = $xt['text'];
            }
            //echo implode('//',$krow).PHP_EOL;
            //echo implode('//',$krow).PHP_EOL;
            //actual row output
            ksort($krow, SORT_NUMERIC);
            if (!empty($out_text)) {
                $out_text .= "\n";
            }
            $out_text .= implode('', $krow);

            //init new row
            $y_max = $y;
            $row = array();
            $row[] = $xtext;
        }
        
        //actual row sort by Y
        $krow = array();
        foreach ($row as $xt) {
            $x = $xt['x'];
            if( isset($krow[$x])){
                $x ++;
            }
            $krow[$x] = $xt['text'];
        }
        //echo 'y:'.$y.'/'.implode('//',$krow).PHP_EOL;
        //last actual row output
        ksort($krow, SORT_NUMERIC);
        if (!empty($out_text)) {
            $out_text .= "\n";
        }
        $out_text .= implode('', $krow);
        return $out_text;        
    }
    
    /**
     * @param string $text_part
     * @param int    $offset
     *
     * @return array
     */
    public function getCommandsText($text_part, &$offset = 0)
    {
        $commands = $matches = array();

        while ($offset < strlen($text_part)) {
            $offset += strspn($text_part, "\x00\x09\x0a\x0c\x0d\x20", $offset);
            $char = $text_part[$offset];

            $operator = '';
            $type     = '';
            $command  = false;

            switch ($char) {
                case '/':
                    $type = $char;
                    if (preg_match(
                        '/^\/([A-Z0-9\._,\+]+\s+[0-9.\-]+)\s+([A-Z]+)\s*/si',
                        substr($text_part, $offset),
                        $matches
                    )
                    ) {
                        $operator = $matches[2];
                        $command  = $matches[1];
                        $offset += strlen($matches[0]);
                    } elseif (preg_match(
                        '/^\/([A-Z0-9\._,\+]+)\s+([A-Z]+)\s*/si',
                        substr($text_part, $offset),
                        $matches
                    )
                    ) {
                        $operator = $matches[2];
                        $command  = $matches[1];
                        $offset += strlen($matches[0]);
                    }
                    break;

                case '[':
                case ']':
                    // array object
                    $type = $char;
                    if ($char == '[') {
                        ++$offset;
                        // get elements
                        $command = $this->getCommandsText($text_part, $offset);

                        if (preg_match('/^\s*[A-Z]{1,2}\s*/si', substr($text_part, $offset), $matches)) {
                            $operator = trim($matches[0]);
                            $offset += strlen($matches[0]);
                        }
                    } else {
                        ++$offset;
                        break;
                    }
                    break;

                case '<':
                case '>':
                    // array object
                    $type = $char;
                    ++$offset;
                    if ($char == '<') {
                        $strpos  = strpos($text_part, '>', $offset);
                        $command = substr($text_part, $offset, ($strpos - $offset));
                        $offset  = $strpos + 1;
                    }

                    if (preg_match('/^\s*[A-Z]{1,2}\s*/si', substr($text_part, $offset), $matches)) {
                        $operator = trim($matches[0]);
                        $offset += strlen($matches[0]);
                    }
                    break;

                case '(':
                case ')':
                    ++$offset;
                    $type   = $char;
                    $strpos = $offset;
                    if ($char == '(') {
                        $open_bracket = 1;
                        while ($open_bracket > 0) {
                            if (!isset($text_part[$strpos])) {
                                break;
                            }
                            $ch = $text_part[$strpos];
                            switch ($ch) {
                                case '\\':
                                { // REVERSE SOLIDUS (5Ch) (Backslash)
                                    // skip next character
                                    ++$strpos;
                                    break;
                                }
                                case '(':
                                { // LEFT PARENHESIS (28h)
                                    ++$open_bracket;
                                    break;
                                }
                                case ')':
                                { // RIGHT PARENTHESIS (29h)
                                    --$open_bracket;
                                    break;
                                }
                            }
                            ++$strpos;
                        }
                        $command = substr($text_part, $offset, ($strpos - $offset - 1));
                        $offset  = $strpos;

                        if (preg_match('/^\s*([A-Z\']{1,2})\s*/si', substr($text_part, $offset), $matches)) {
                            $operator = $matches[1];
                            $offset += strlen($matches[0]);
                        }
                    }
                    break;

                default:

                    if (substr($text_part, $offset, 2) == 'ET') {
                        break;
                    } elseif (preg_match(
                        '/^\s*(?P<data>([0-9\.\-]+\s*?)+)\s+(?P<id>[A-Z]{1,3})\s*/si',
                        substr($text_part, $offset),
                        $matches
                    )
                    ) {
                        $operator = trim($matches['id']);
                        $command  = trim($matches['data']);
                        $offset += strlen($matches[0]);
                    } elseif (preg_match('/^\s*([0-9\.\-]+\s*?)+\s*/si', substr($text_part, $offset), $matches)) {
                        $type    = 'n';
                        $command = trim($matches[0]);
                        $offset += strlen($matches[0]);
                    } elseif (preg_match('/^\s*([A-Z\*]+)\s*/si', substr($text_part, $offset), $matches)) {
                        $type     = '';
                        $operator = $matches[1];
                        $command  = '';
                        $offset += strlen($matches[0]);
                    }
            }

            if ($command !== false) {
                $commands[] = array(
                    self::TYPE     => $type,
                    self::OPERATOR => $operator,
                    self::COMMAND  => $command,
                );
            } else {
                break;
            }
        }

        return $commands;
    }

    /**
     * @param $document Document
     * @param $header   Header
     * @param $content  string
     *
     * @return Object
     */
    public static function factory(Document $document, Header $header, $content)
    {
        switch ($header->get('Type')->getContent()) {
            case 'XObject':
                switch ($header->get('Subtype')->getContent()) {
                    case 'Image':
                        return new Image($document, $header, $content);

                    case 'Form':
                        return new Form($document, $header, $content);

                    default:
                        return new Object($document, $header, $content);
                }
                break;

            case 'Pages':
                return new Pages($document, $header, $content);

            case 'Page':
                return new Page($document, $header, $content);

            case 'Encoding':
                return new Encoding($document, $header, $content);

            case 'Font':
                $subtype   = $header->get('Subtype')->getContent();
                $classname = '\Smalot\PdfParser\Font\Font' . $subtype;

                if (class_exists($classname)) {
                    return new $classname($document, $header, $content);
                } else {
                    return new Font($document, $header, $content);
                }

            default:
                return new Object($document, $header, $content);
        }
    }
}
