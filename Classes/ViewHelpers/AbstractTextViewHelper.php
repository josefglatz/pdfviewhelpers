<?php

namespace Bithost\Pdfviewhelpers\ViewHelpers;

/* * *
 *
 * This file is part of the "PDF ViewHelpers" Extension for TYPO3 CMS.
 *
 *  (c) 2016 Markus Mächler <markus.maechler@bithost.ch>, Bithost GmbH
 *           Esteban Marin <esteban.marin@bithost.ch>, Bithost GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * * */

use Bithost\Pdfviewhelpers\Exception\Exception;
use Bithost\Pdfviewhelpers\Exception\ValidationException;

/**
 * AbstractTextViewHelper
 *
 * @author Markus Mächler <markus.maechler@bithost.ch>, Esteban Marin <esteban.marin@bithost.ch>
 */
abstract class AbstractTextViewHelper extends AbstractContentElementViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerArgument('trim', 'boolean', '', false, $this->settings['generalText']['trim']);
        $this->registerArgument('removeDoubleWhitespace', 'boolean', '', false, $this->settings['generalText']['removeDoubleWhitespace']);
        $this->registerArgument('color', 'string', '', false, $this->settings['generalText']['color']);
        $this->registerArgument('fontFamily', 'string', '', false, $this->settings['generalText']['fontFamily']);
        $this->registerArgument('fontSize', 'integer', '', false, $this->settings['generalText']['fontSize']);
        $this->registerArgument('fontStyle', 'string', '', false, $this->settings['generalText']['fontStyle']);
        $this->registerArgument('padding', 'array', '', false, []);
        $this->registerArgument('text', 'string', '', false, null);
        $this->registerArgument('alignment', 'string', 'Text Alignment. Possible values: "left", "center", "right", "justify". Defaults to "left"', false, $this->settings['generalText']['alignment']);
        $this->registerArgument('paragraphSpacing', 'float', 'Spacing after each paragraph. Defaults to 0', false, $this->settings['generalText']['paragraphSpacing']);
        $this->registerArgument('autoHyphenation', 'boolean', '', false, $this->settings['generalText']['autoHyphenation']);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function initialize()
    {
        parent::initialize();

        if (empty($this->arguments['text'])) {
            $this->arguments['text'] = $this->renderChildren();
        }

        if ($this->arguments['trim']) {
            $this->arguments['text'] = trim($this->arguments['text']);
        }

        if ($this->arguments['removeDoubleWhitespace']) {
            $this->arguments['text'] = preg_replace('/[ \t]+/', ' ', $this->arguments['text']);
        }

        if ($this->arguments['autoHyphenation']) {
            $this->arguments['text'] = $this->hyphenateText($this->arguments['text']);
        }

        if ($this->isValidColor($this->arguments['color'])) {
            $this->arguments['color'] = $this->convertHexToRGB($this->arguments['color']);
            $this->getPDF()->SetTextColor($this->arguments['color']['R'], $this->arguments['color']['G'], $this->arguments['color']['B']);
        }

        if ($this->isValidFontSize($this->arguments['fontSize'])) {
            $this->getPDF()->SetFontSize($this->arguments['fontSize']);
        }

        if ($this->isValidFontFamily($this->arguments['fontFamily'])) {
            $this->getPDF()->SetFont($this->arguments['fontFamily'], $this->convertToTcpdfFontStyle($this->arguments['fontStyle']));
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function render()
    {
        $this->initializeMultiColumnSupport();

        $paragraphs = explode("\n", str_replace("\r\n", "\n", $this->arguments['text']));
        $posY = $this->arguments['posY'];

        foreach ($paragraphs as $paragraph) {
            if ($this->arguments['trim']) {
                $paragraph = trim($paragraph);
            }

            $this->getPDF()->MultiCell($this->arguments['width'], $this->arguments['height'] / count($paragraphs), $paragraph, 0, $this->convertToTcpdfAlignment($this->arguments['alignment']), false, 1, $this->arguments['posX'], $posY, true, 0, false, true, 0, 'T', false);

            if ($this->isValidParagraphSpacing($this->arguments['paragraphSpacing']) && $this->arguments['paragraphSpacing'] > 0
            ) {
                $this->getPDF()->Ln((float)$this->arguments['paragraphSpacing'], false);
            }

            $posY = $this->getPDF()->GetY();
        }
    }

    /**
     * @param string $fontSize
     *
     * @return boolean
     *
     * @throws ValidationException
     */
    protected function isValidFontSize($fontSize)
    {
        if (is_numeric($fontSize)) {
            return true;
        } else {
            throw new ValidationException('FontSize must be an integer. ERROR: 1363765372', 1363765372);
        }
    }

    /**
     * @param string $paragraphSpacing
     *
     * @return boolean
     *
     * @throws ValidationException
     */
    protected function isValidParagraphSpacing($paragraphSpacing)
    {
        if (is_numeric($paragraphSpacing)) {
            return true;
        } else {
            throw new ValidationException('ParagraphSpacing must be an integer. ERROR: 1363765379', 1363765379);
        }
    }

    /**
     * Check fontFamily for compatibility with TCPDF naming conventions
     *
     * @param string $fontFamily
     *
     * @return boolean
     *
     * @throws ValidationException
     */
    protected function isValidFontFamily($fontFamily)
    {
        //TCPDF transformation START
        $tcpdfFontFamilyName = strtolower($fontFamily);
        $tcpdfFontFamilyName = preg_replace('/[^a-z0-9_]/', '', $tcpdfFontFamilyName);
        $search = ['bold', 'oblique', 'italic', 'regular'];
        $replace = ['b', 'i', 'i', ''];
        $tcpdfFontFamilyName = str_replace($search, $replace, $tcpdfFontFamilyName);
        if (empty($tcpdfFontFamilyName)) {
            // set generic name
            $tcpdfFontFamilyName = 'tcpdffont';
        }
        //TCPDF transformation END

        if ($fontFamily === $tcpdfFontFamilyName) {
            return true;
        } else {
            throw new ValidationException('Invalid fontFamily name "' . $fontFamily . '". Name must only contain letters "a-z0-9_" and none of the words "bold", "oblique", "italic" and "regular". ERROR: 1492809393', 1492809393);
        }
    }

    /**
     * @param string $alignment
     *
     * @return string
     *
     * @throws Exception
     */
    protected function convertToTcpdfAlignment($alignment)
    {
        switch ($alignment) {
            case 'left':
            case 'L':
                return 'L';
            case 'center':
            case 'C':
                return 'C';
            case 'right':
            case 'R':
                return 'R';
            case 'justify':
            case 'J':
                return 'J';
            default:
                throw new ValidationException('Invalid alignment "' . $alignment . '" provided. ERROR: 1536237714', 1536237714);
        }
    }
}
