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

/**
 * Plugin event observers are registered here.
 *
 * @package local_shopping_cart
 * @copyright 2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\output;

use plugin_renderer_base;
use templatable;

/**
 * Renderer class.
 * @package local_shopping_cart
 */
class renderer extends plugin_renderer_base {

    /**
     * Render add to cart button
     *
     * @param templatable $button
     * @return string|bool
     */
    public function render_button(templatable $button) {
        $data = $button->export_for_template($this);
        return $this->render_from_template('local_shopping_cart/addtocartdb', $data);
    }

    /**
     * Render history card.
     *
     * @param templatable $data
     * @return string|bool
     */
    public function render_history_card(templatable $data) {
        $data = $data->export_for_template($this);
        return $this->render_from_template('local_shopping_cart/history_card', $data);
    }
}
