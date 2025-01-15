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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process;

use cache;
use Exception;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkout_manager {
    /**
     * Namespace prefix for item classes.
     */
    private const NAMESPACE_PREFIX = 'local_shopping_cart\\local\\checkout_process\\items\\';
    /**
     * Optional properties for the checkout manager.
     * @var array
     */
    private $controlparameter;
    /**
     * Optional properties for the checkout manager.
     * @var string
     */
    private $identifier;
    /**
     * Optional properties for the checkout manager.
     * @var array
     */
    private $managercache;
    /**
     * Optional properties for the checkout manager.
     * @var array
     */
    private $itemlist;
    /**
     * Optional properties for the checkout manager.
     * @var array
     */
    private $cartstoredata;

    /**
     * Constructor with optional parameters.
     *
     * @param string $identifier Optional identifier.
     * @param array $controlparameter Optional controlparameter.
     */
    public function __construct(
        $data,
        $controlparameter = null
    ) {
        global $CFG;
        $this->cartstoredata = $data;
        $this->identifier = $data['userid'];
        $this->controlparameter = $controlparameter;
        $this->managercache = self::get_cache($data['userid']);
        $this->itemlist = self::get_itemlist_preprocess();
        self::set_body_mandatory_count();
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function render_overview() {
        $checkoutmanager = [
            'checkout_manager_head' => [],
            'checkout_manager_body' => [],
        ];
        $currentstep = self::set_current_step();

        self::set_manager_data($checkoutmanager, $currentstep);

        self::set_active_page($checkoutmanager['checkout_manager_body']['item_list'], $currentstep);

        self::render_body_buttons($checkoutmanager['checkout_manager_body'], $currentstep);

        self::render_checkout_head($checkoutmanager['checkout_manager_head']);
        return $checkoutmanager;
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function set_manager_data(&$checkoutmanager, $currentstep) {
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (self::class_exists_is_active($classname)) {
                $iteminstance = new $classname();
                $renderdestination = $iteminstance->is_head() ? 'head' : 'body';
                $checkoutmanager['checkout_manager_' . $renderdestination]['item_list'][] = [
                    'item' => $iteminstance->get_icon_progress_bar(),
                    'status' => 'inactive',
                    'valid' => self::is_step_valid($classname),
                    'classname' => $classname,
                ];
            }
        }
        $checkoutmanager['checkout_manager_body']['show_progress_line'] =
            count($checkoutmanager['checkout_manager_body']['item_list']) > 1 ? true : false;
        $checkoutmanager['checkout_manager_body']['currentstep'] = $currentstep;
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function render_body_buttons(&$checkoutmanagerbody, $currentstep) {
        if (self::has_multiple_items($checkoutmanagerbody)) {
            $checkoutmanagerbody['buttons'] =
                self::render_navigation_buttons(
                    $checkoutmanagerbody['item_list'],
                    $currentstep
                );
        }
        $checkoutmanagerbody['buttons']['checkout_button'] =
                self::render_checkout_button();
        $checkoutmanagerbody['body'] =
            self::render_checkout_body($checkoutmanagerbody['item_list']);
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function get_itemlist_preprocess() {
        global $CFG;
        $path = $CFG->dirroot . '/local/shopping_cart/classes/local/checkout_process/items/*';
        return glob($path . '*.php');
    }


    /**
     * Applies the given price modifiers on the cached data.
     */
    public function check_preprocess($changedinput) {
        $bodycounter = 0;
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (self::class_exists_is_active($classname)) {
                $iteminstance = new $classname();
                if ($bodycounter == $this->controlparameter['currentstep']) {
                    $this->managercache['steps'][$filename] = $iteminstance->check_status(
                        $this->managercache['steps'][$filename],
                        $changedinput
                    );
                    if ($this->managercache['steps'][$filename]['valid']) {
                        $this->managercache['feedback'] = [
                            'validationmessage' => $iteminstance->get_validation_feedback(),
                        ];
                    } else {
                        $this->managercache['feedback'] = [
                            'errormessage' => $iteminstance->get_error_feedback(),
                        ];
                    }
                }
                if ($iteminstance->is_head() === false) {
                    $bodycounter += 1;
                } else {
                    $this->managercache['steps'][$filename] = $iteminstance->check_status(
                        $this->managercache['steps'][$filename],
                        $changedinput
                    );
                }
            }
        }
        self::get_checkout_validation();
        self::set_cache();
        return $this->managercache;
    }

    /**
     * Sets the body and mandatory count if not there yet.
     */
    public function set_body_mandatory_count() {
        if (!isset($this->managercache['body_mandatory_count'])) {
            $bodycounter = 0;
            $mandatorycounter = 0;
            foreach ($this->itemlist as $item) {
                $filename = basename($item, '.php');
                $classname = self::NAMESPACE_PREFIX . $filename;
                if (self::class_exists_is_active($classname)) {
                    $iteminstance = new $classname();
                    if ($iteminstance->is_head() === false) {
                        $bodycounter += 1;
                    }
                    if ($iteminstance->is_mandatory()) {
                        $mandatorycounter += 1;
                    }
                }
            }
            $this->managercache['body_mandatory_count'] = [
                'body_count' => $bodycounter,
                'mandatory_count' => $mandatorycounter,
            ];
            self::set_cache();
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function get_checkout_validation() {
        $mandatorycachedcounter = $this->managercache['body_mandatory_count']['mandatory_count'];
        $mandatorycurrentcounter = 0;
        $bodycounter = $this->managercache['body_mandatory_count']['body_count'];
        $this->managercache['checkout_validation'] = false;
        if (isset($this->managercache['steps'])) {
            foreach ($this->managercache['steps'] as $step) {
                if ($step['mandatory'] === true) {
                    if ($step['valid'] === false) {
                        return;
                    } else {
                        $mandatorycurrentcounter++;
                    }
                }
            }
            if (self::is_checkout_allowed($mandatorycachedcounter, $mandatorycurrentcounter, $bodycounter)) {
                $this->managercache['checkout_validation'] = true;
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function is_checkout_allowed($cachedcounter, $currentcounter, $bodycounter) {
        return (
            $cachedcounter <= $currentcounter &&
            $bodycounter <= count($this->managercache['viewed'] ?? [])
        );
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function get_cache($identifier) {
        $cache = cache::make('local_shopping_cart', 'cachebookingpreprocess');
        if (!$cache->has($identifier)) {
            $cache->set($identifier, []);
        }
        return $cache->get($identifier);
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function set_cache() {
        $cache = cache::make('local_shopping_cart', 'cachebookingpreprocess');
        $cache->set($this->identifier, $this->managercache);
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function set_active_page(&$itemlist, $currentstep) {
        foreach ($itemlist as $key => &$item) {
            $item['step'] = $key;
            if ($key == $currentstep) {
                $item['status'] = 'active';
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function set_current_step() {
        if (!isset($this->controlparameter['currentstep'])) {
            return 0;
        } else {
            return $this->controlparameter['currentstep'] + self::get_pagination_action();
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function get_pagination_action() {
        if ($this->controlparameter['action'] == 'next') {
            return 1;
        } else if ($this->controlparameter['action'] == 'previous') {
            return -1;
        }
        return 0;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public function render_checkout_head(&$checkoutmanagerhead) {
        if (isset($checkoutmanagerhead['item_list'])) {
            $checkoutmanagerhead['body'] = [];
            foreach ($checkoutmanagerhead['item_list'] as $item) {
                if (self::class_exists_is_active($item['classname'])) {
                    $iteminstance = new $item['classname']();
                    $checkoutmanagerhead['head'][] = $iteminstance->render_body($this->cartstoredata);
                }
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public function render_checkout_body($itemlist) {
        try {
            foreach ($itemlist as $item) {
                if ($item['status'] == 'active') {
                    $iteminstance = new $item['classname']();
                    $classname = self::get_class_name($item['classname']);
                    if (!isset($this->managercache['viewed'])) {
                        $this->managercache['viewed'] = [];
                    }
                    $this->managercache['viewed'][$classname] = true;
                    self::get_checkout_validation();
                    self::set_cache();
                    return $iteminstance->render_body($this->managercache['steps'][$classname] ?? []);
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public static function set_first_step_active(&$itemlist) {
        if (isset($itemlist[1])) {
            $itemlist[1]['status'] = 'active';
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public function render_checkout_button() {
        return $this->managercache['checkout_validation'] ?? false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $classname
     * @return array
     */
    public function render_navigation_buttons($itemlist, $currentstep) {
        $previousbutton = [
            'text' => get_string('previousbutton', 'local_shopping_cart'),
            'hidden' => $currentstep == 0 ? true : false,
        ];
        $nextbutton = [
            'text' => get_string('nextbutton', 'local_shopping_cart'),
            'hidden' => $currentstep == (count($itemlist) - 1) ? true : false,
            'disabled' => !self::is_step_valid($itemlist[$currentstep]['classname']),
        ];
        return [
            'previous_button' => $previousbutton,
            'next_button' => $nextbutton,
        ];
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public function is_step_valid($classnamepath) {
        $classname = self::get_class_name($classnamepath);
        if (isset($this->managercache['steps'][$classname]['valid'])) {
            return $this->managercache['steps'][$classname]['valid'];
        }
        return false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return string
     */
    public function get_class_name($classnamepath) {
        $parts = explode('\\', $classnamepath);
        return end($parts);
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public static function class_exists_is_active($classname) {
        if (class_exists($classname)) {
            $iteminstance = new $classname();
            if ($iteminstance->is_active()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public static function has_multiple_items($body) {
        if (
            isset($body['item_list']) &&
            count($body['item_list']) > 1
        ) {
            return true;
        }
        return false;
    }
}
