<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

/**
 * Functions for SAP text files (daily SAP sums for M:USI).
 *
 * @package local_shopping_cart
 * @since Moodle 4.0
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use context_system;
use core_user;
use Exception;
use html_writer;
use local_entities\entitiesrelation_handler;
use local_shopping_cart\addresses;
use local_shopping_cart\invoice\invoicenumber;
use local_shopping_cart\shopping_cart_history;
use mod_booking\booking_option_settings;
use moodle_exception;
use stored_file;
use TCPDF;

// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

/**
 * Class WBPDF
 *
 * @author Stephan Lorbek
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class WBPDF extends TCPDF {
    /**
     * Parse HTML tags.
     * @param string $content
     * @param string $tagname
     * @return string
     */
    private function parse_tags(string $content, string $tagname): string {
        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $tags = $dom->getElementsByTagName($tagname);

        if ($tags->length > 0) {
            $element = $tags->item(0);
            $innerhtml = '';
            foreach ($element->childNodes as $child) {
                $innerhtml .= $dom->saveHTML($child);
            }
            return $innerhtml;
        }

        return '';
    }

    /**
     * Check if tag is present in HTML.
     * @param string $content
     * @param string $tagname
     * @return bool
     */
    public function is_tag_present(string $content, string $tagname): bool {
        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $tags = $dom->getElementsByTagName($tagname);
        return $tags->length > 0;
    }

    /**
     * Strip content of a specific HTML tag.
     * @param string $content
     * @param string $tagname
     * @return array|string|null
     */
    public function strip_content(string $content, string $tagname): array|string|null {
        $pattern = sprintf('#<%1$s\b[^>]*>.*?</%1$s>#is', preg_quote($tagname, '#'));
        return preg_replace($pattern, '', $content);
    }

    /**
     * Write PDF header function - overrides the TCPDF Header method.
     * @return void
     */
    public function Header(): void {
        $content = get_config('local_shopping_cart', 'receipthtml') ?? '';
        if (!$this->is_tag_present($content, 'header')) {
            return;
        }
        $header = $this->parse_tags($content, 'header');
        $this->SetFont('helvetica', 'B', 20);
        $this->writeHTMLCell(0, 0, '', '', $header, 0, 1, 0, true, 'L', true);
    }

    /**
     * Write PDF footer function - overrides the TCPDF Footer method.
     * @return void
     */
    public function Footer(): void {
        $content = get_config('local_shopping_cart', 'receipthtml') ?? '';

        if (!$this->is_tag_present($content, 'footer')) {
            return;
        }

        $this->SetY(-220);
        $this->SetFont('helvetica', '', 7);

        $autopagebreak = $this->AutoPageBreak;
        $bmargin = $this->bMargin;
        $this->SetAutoPageBreak(false, 0);

        // Write footer HTML content.
        $footer = $this->parse_tags($content, 'footer');
        $this->writeHTMLCell(0, 0, '', '', $footer, 0, 1, 0, true, 'L', true);

        // Restore autopagebreak.
        $this->SetAutoPageBreak($autopagebreak, $bmargin);

        // Page number section (always bottom-right).
        $this->SetY(-20); // 20mm from bottom.
        $this->SetFont('helvetica', '', 7);

        $pagenum = get_string("page") . ' ' .
            $this->getAliasNumPage() . '/' .
            $this->getAliasNbPages();

        $this->Cell(0, 10, $pagenum, 0, 0, 'R');
    }
}
