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
     * @param array $data
     * @param array $controlparameter Optional controlparameter.
     */
    public function __construct(
        $data,
        $controlparameter = null
    ) {
        $this->cartstoredata = $data;
        $this->identifier = $data['userid'];
        $this->controlparameter = $controlparameter;
        $this->managercache = self::get_cache($data['userid']);
        $this->itemlist = self::get_itemlist_preprocess();
        self::apply_voluntarily_vat_toggle();
        self::set_body_mandatory_count();
    }

    /**
     * Persists a change of the voluntarily-VAT checkbox into the manager cache
     * before the mandatory count is computed.
     *
     * Ticking the checkbox makes the VAT step mandatory (vatnrchecker::is_mandatory
     * reads this cached flag), so a change must also invalidate the cached
     * mandatory count so it is recomputed for this request.
     *
     * @return void
     */
    public function apply_voluntarily_vat_toggle(): void {
        $changedinput = $this->controlparameter['changedinput'] ?? null;
        if (!is_string($changedinput)) {
            return;
        }
        $parsed = json_decode($changedinput);
        if (empty($parsed)) {
            return;
        }
        foreach ($parsed as $input) {
            if (isset($input->name) && $input->name === 'vatnumbervoluntarily') {
                $newvalue = !empty($input->value);
                $oldvalue = !empty($this->managercache['vatnumbervoluntarily']);
                if ($newvalue !== $oldvalue) {
                    $this->managercache['vatnumbervoluntarily'] = $newvalue;
                    unset($this->managercache['body_mandatory_count']);
                    self::set_cache();
                }
                break;
            }
        }
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

        self::set_vat_number_checkbox($checkoutmanager);

        self::render_body_buttons($checkoutmanager['checkout_manager_body'], $currentstep);

        self::render_checkout_head($checkoutmanager['checkout_manager_head']);

        self::set_feedback($checkoutmanager['checkout_manager_body']);
        return $checkoutmanager;
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $checkoutmanager
     *
     * @return void
     *
     */
    public function set_vat_number_checkbox(&$checkoutmanager): void {
        $showvatnrchecker = get_config('local_shopping_cart', 'showvatnrchecker');
        $onlywithvatnrnumber = get_config('local_shopping_cart', 'onlywithvatnrnumber');
        if (
            $showvatnrchecker &&
            !$onlywithvatnrnumber &&
            $checkoutmanager['checkout_manager_body']['currentstep'] == '0'
        ) {
            $owncountry = get_config('local_shopping_cart', 'owncountrycode');
            $vatvoluntarily = false;
            $changedinputs = json_decode($this->controlparameter['changedinput'] ?? '[]');
            if (!empty($changedinputs)) {
                foreach ($changedinputs as $changedinput) {
                    if (isset($changedinput->name) && $changedinput->name == 'vatnumbervoluntarily') {
                        $vatvoluntarily = $changedinput->value;
                        $this->managercache['vatnumbervoluntarily'] = $vatvoluntarily;
                        continue;
                    }
                }
            } else {
                $vatvoluntarily = $this->managercache['vatnumbervoluntarily'] ?? false;
            }
            if (
                !get_config('local_shopping_cart', 'onlywithvatnrnumber') &&
                $owncountry
            ) {
                $checkoutmanager['checkout_manager_body']['vat_number_voluntarily'] = [
                    'active' => $vatvoluntarily,
                    'text' => get_string('vatnumbervoluntarily', 'local_shopping_cart', $owncountry),
                ];
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param mixed $checkoutmanagerbody
     * @return void
     *
     */
    public function set_feedback(&$checkoutmanagerbody): void {
        $currentstep = $checkoutmanagerbody['currentstep'];
        if (isset($checkoutmanagerbody['item_list'][$currentstep])) {
            $item = $checkoutmanagerbody['item_list'][$currentstep];
            if (self::class_exists_is_active($item['classname'])) {
                $iteminstance = new $item['classname']($this->identifier);
                $checkoutmanagerbody['feedback'] = $iteminstance->get_info_feedback($this->cartstoredata);
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $checkoutmanager
     * @param mixed $currentstep
     *
     * @return void
     *
     */
    public function set_manager_data(&$checkoutmanager, $currentstep): void {
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (self::class_exists_is_active($classname)) {
                $iteminstance = new $classname($this->identifier);
                $renderdestination = $iteminstance->is_head() ? 'head' : 'body';
                $checkoutmanager['checkout_manager_' . $renderdestination]['item_list'][] = [
                    'item' => $iteminstance->get_icon_progress_bar(),
                    'status' => 'inactive',
                    'valid' => self::is_step_valid($classname),
                    'mandatory' => $iteminstance->is_mandatory(),
                    'classname' => $classname,
                ];
            }
        }
        $checkoutmanager['checkout_manager_body']['show_progress_line'] =
            count(($checkoutmanager['checkout_manager_body']['item_list']) ?? []) > 1 ? true : false;
        $checkoutmanager['checkout_manager_body']['currentstep'] = $currentstep;
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $checkoutmanagerbody
     * @param mixed $currentstep
     *
     * @return void
     *
     */
    public function render_body_buttons(&$checkoutmanagerbody, $currentstep): void {
        if (self::has_multiple_items($checkoutmanagerbody)) {
            $checkoutmanagerbody['buttons'] =
                self::render_navigation_buttons(
                    $checkoutmanagerbody['item_list'],
                    $currentstep
                );
        }
        $checkoutmanagerbody['body'] =
            self::render_checkout_body($checkoutmanagerbody['item_list']);

        if (empty($checkoutmanagerbody['item_list'])) {
            $checkoutmanagerbody['buttons']['checkout_button'] = true;
        } else {
            $checkoutmanagerbody['buttons']['checkout_button'] =
                self::render_checkout_button();
        }
        if (
            !get_config('local_shopping_cart', 'showdisabledcheckoutbutton') &&
            !$checkoutmanagerbody['buttons']['checkout_button']
        ) {
            $checkoutmanagerbody['buttons']['hide_disabled_checkout_button'] = true;
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @return array
     */
    public function get_itemlist_preprocess() {
        global $CFG;
        $path = $CFG->dirroot . '/local/shopping_cart/classes/local/checkout_process/items/*';
        $items = glob($path . '*.php');
        $sorteditems = [];
        foreach ($items as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            $iteminstance = new $classname($this->identifier);
            $sorteditems[$iteminstance->get_ordernumber()] = $item;
        }
        ksort($sorteditems, SORT_NUMERIC);
        return array_values($sorteditems);
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $changedinput
     *
     * @return array
     *
     */
    public function check_preprocess($changedinput): array {
        $changedinputsparsed = json_decode($changedinput ?? '[]');
        if (!empty($changedinputsparsed)) {
            foreach ($changedinputsparsed as $input) {
                if (isset($input->name) && $input->name === 'vatnumbervoluntarily') {
                    $this->managercache['vatnumbervoluntarily'] = $input->value;
                    break;
                }
            }
        }
        $bodycounter = 0;
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (
                self::class_exists_is_active($classname)
            ) {
                $iteminstance = new $classname($this->identifier);
                // Steps migrated to dynamic forms write their own validity via
                // process_dynamic_submission() - never overwrite it here.
                $isformstep = !empty($classname::get_form_classname());
                if (
                    !$isformstep
                    && $bodycounter === ($this->controlparameter['currentstep'] ?? null)
                ) {
                    $this->managercache['steps'][$filename] = $iteminstance->check_status(
                        $this->managercache['steps'][$filename] ?? [],
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
                } else if (!$isformstep) {
                    $this->managercache['steps'][$filename] = $iteminstance->check_status(
                        $this->managercache['steps'][$filename] ?? [],
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
     * Programmatically submit the current checkout step with raw step input.
     *
     * Mirrors what a dynamic-form submission does for a form step (validate +
     * persist + update the checkout cache via the item's evaluate_step()), and
     * falls back to the legacy check_status() for non-migrated steps. Driven by
     * a changedinput string so it can be used by integration tests and any
     * non-JS submission path. The current step is taken from the control
     * parameter's currentstep (body index).
     *
     * @param string $changedinput
     * @return array the updated manager cache
     */
    public function submit_step($changedinput): array {
        $bodycounter = 0;
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (!self::class_exists_is_active($classname)) {
                continue;
            }
            $iteminstance = new $classname($this->identifier);
            if ($iteminstance->is_head()) {
                // Head items (e.g. credits) have no interactive submission.
                continue;
            }
            if ($bodycounter === ($this->controlparameter['currentstep'] ?? null)) {
                if (!empty($classname::get_form_classname())) {
                    // Migrated step: validate via the shared item core.
                    $parsed = $iteminstance->parse_changed_input($changedinput);
                    $this->managercache['steps'][$filename] = $iteminstance->evaluate_step($parsed);
                } else {
                    // Legacy step.
                    $this->managercache['steps'][$filename] = $iteminstance->check_status(
                        $this->managercache['steps'][$filename] ?? [],
                        $changedinput
                    );
                }
                if ($this->managercache['steps'][$filename]['valid']) {
                    $this->managercache['feedback'] = [
                        'validationmessage' => $iteminstance->get_validation_feedback(),
                    ];
                } else {
                    $this->managercache['feedback'] = [
                        'errormessage' => $iteminstance->get_error_feedback(),
                    ];
                }
                break;
            }
            $bodycounter += 1;
        }
        self::get_checkout_validation();
        self::set_cache();
        return $this->managercache;
    }

    /**
     * Sets the body and mandatory count if not there yet.
     */
    public function set_body_mandatory_count(): void {
        if (!isset($this->managercache['body_mandatory_count'])) {
            $bodycounter = 0;
            $mandatorycounter = 0;
            foreach ($this->itemlist as $item) {
                $filename = basename($item, '.php');
                $classname = self::NAMESPACE_PREFIX . $filename;
                if (self::class_exists_is_active($classname)) {
                    $iteminstance = new $classname($this->identifier);
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
    public function get_checkout_validation(): void {
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
     *
     * @param mixed $cachedcounter
     * @param mixed $currentcounter
     * @param mixed $bodycounter
     *
     * @return bool
     *
     */
    public function is_checkout_allowed($cachedcounter, $currentcounter, $bodycounter): bool {
        return (
            $cachedcounter <= $currentcounter &&
            $bodycounter <= count($this->managercache['viewed'] ?? [])
        );
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $identifier
     *
     */
    public static function get_cache($identifier) {
        $cache = cache::make('local_shopping_cart', 'cachebookingpreprocess');
        if (!$cache->has($identifier)) {
            $cache->set($identifier, []);
        }
        return $cache->get($identifier);
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function set_cache(): void {
        $cache = cache::make('local_shopping_cart', 'cachebookingpreprocess');
        $cache->set($this->identifier, $this->managercache);
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $itemlist
     * @param mixed $currentstep
     *
     * @return void
     *
     */
    public function set_active_page(&$itemlist, $currentstep): void {
        if (empty($itemlist)) {
            return;
        }
        foreach (($itemlist) as $key => &$item) {
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
    public function get_pagination_action(): int {
        if ($this->controlparameter['action'] == 'next') {
            return 1;
        } else if ($this->controlparameter['action'] == 'previous') {
            return -1;
        }
        return 0;
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $checkoutmanagerhead
     *
     * @return void
     *
     */
    public function render_checkout_head(&$checkoutmanagerhead): void {
        if (isset($checkoutmanagerhead['item_list'])) {
            $checkoutmanagerhead['body'] = [];
            foreach ($checkoutmanagerhead['item_list'] as $item) {
                if (self::class_exists_is_active($item['classname'])) {
                    $iteminstance = new $item['classname']($this->identifier);
                    $checkoutmanagerhead['head'][] = $iteminstance->render_body($this->cartstoredata);
                }
            }
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $itemlist
     *
     * @return array
     *
     */
    public function render_checkout_body($itemlist): array {
        if (empty($itemlist)) {
            return [];
        }
        try {
            foreach ($itemlist as $item) {
                if ($item['status'] == 'active') {
                    $iteminstance = new $item['classname']($this->identifier);
                    $classname = self::get_class_name($item['classname']);
                    if (!isset($this->managercache['viewed'])) {
                        $this->managercache['viewed'] = [];
                    }
                    $this->managercache['viewed'][$classname] = true;
                    self::get_checkout_validation();
                    self::set_cache();
                    $formclass = $item['classname']::get_form_classname();
                    if (!empty($formclass)) {
                        // Dynamic-form step: pre-render the form server-side and let
                        // the JS hydrate it (Moodle dynamic-forms pattern), avoiding
                        // an extra load() roundtrip and a flash of empty content.
                        global $PAGE;
                        $surroundings = $item['classname']::render_form_surroundings();
                        $form = new $formclass(null, null, 'post', '', [], true, null, false);
                        $form->set_data_for_dynamic_submission();
                        $template = $PAGE->get_renderer('local_shopping_cart')->render_from_template(
                            'local_shopping_cart/checkout_step_form_container',
                            [
                                'formclass' => $formclass,
                                'stepkey' => $classname,
                                'autosubmit' => $formclass::is_autosubmit(),
                                'formhtml' => $form->render(),
                                'beforeform' => $surroundings['before'] ?? '',
                                'afterform' => $surroundings['after'] ?? '',
                                'wrapperclass' => $surroundings['wrapperclass'] ?? '',
                            ]
                        );
                        return ['template' => $template];
                    }
                    return $iteminstance->render_body($this->managercache['steps'][$classname] ?? []);
                }
            }
        } catch (Exception $e) {
            return [];
        }
        return [];
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param array $itemlist
     *
     * @return void
     */
    public static function set_first_step_active(&$itemlist): void {
        if (isset($itemlist[1])) {
            $itemlist[1]['status'] = 'active';
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     */
    public function render_checkout_button() {
        return $this->managercache['checkout_validation'] ?? false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     *
     * @param mixed $itemlist
     * @param mixed $currentstep
     *
     * @return array
     *
     */
    public function render_navigation_buttons($itemlist, $currentstep): array {
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
     * @param string $classnamepath
     */
    public function is_step_valid($classnamepath) {
        $classname = self::get_class_name($classnamepath);
        if (
            isset($this->managercache['steps'])
            && isset($this->managercache['steps'][$classname])
            && !$this->managercache['steps'][$classname]['mandatory']
        ) {
            return true;
        }
        if (isset($this->managercache['steps'][$classname]['valid'])) {
            return $this->managercache['steps'][$classname]['valid'];
        }
        return false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classnamepath
     * @return string
     */
    public function get_class_name($classnamepath): string {
        $parts = explode('\\', $classnamepath);
        return end($parts);
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public function class_exists_is_active($classname): bool {
        if (class_exists($classname)) {
            $iteminstance = new $classname($this->identifier);
            if (
                $iteminstance->is_active($this->controlparameter['changedinput'] ?? [], $this->managercache ?? [])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $body
     * @return bool
     */
    public static function has_multiple_items($body): bool {
        if (
            isset($body['item_list']) &&
            count($body['item_list']) > 1
        ) {
            return true;
        }
        return false;
    }

    /**
     * Returns the body-step index of the given step key (for the active-page marker).
     *
     * @param string $stepkey Short classname of the step item.
     * @return int
     */
    public function get_body_index_for_step(string $stepkey): int {
        $bodycounter = 0;
        foreach ($this->itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = self::NAMESPACE_PREFIX . $filename;
            if (self::class_exists_is_active($classname)) {
                $iteminstance = new $classname($this->identifier);
                if ($iteminstance->is_head() === false) {
                    if ($filename === $stepkey) {
                        return $bodycounter;
                    }
                    $bodycounter += 1;
                }
            }
        }
        return 0;
    }

    /**
     * Persists the result of a dynamic-form step submission and returns the
     * partial-update payload in the same shape as control_checkout_process,
     * so the existing checkout_manager JS can re-render buttons/progress.
     *
     * @param int $userid
     * @param string $stepkey Short classname of the step item.
     * @param array $stepresult ['data' => array, 'valid' => bool, 'mandatory' => bool]
     * @return array
     */
    public static function store_form_step_result(int $userid, string $stepkey, array $stepresult): array {
        $cartstore = \local_shopping_cart\local\cartstore::instance($userid);
        $data = $cartstore->get_localized_data();

        // get_localized_data() does not set identifier; recover it from session cache so the
        // re-rendered payment button keeps a valid data-itemid (empty → invalidparameter error).
        $data['userid'] = $userid;
        $schistory = \cache::make('local_shopping_cart', 'schistory')->get('schistorycache');
        $identifier = (string)($schistory['identifier'] ?? '');
        $data = \local_shopping_cart\local\pricemodifier\modifiers\checkout::prepare_checkout($data, $identifier);

        $manager = new self($data, []);
        $manager = new self($data, [
            'currentstep' => $manager->get_body_index_for_step($stepkey),
            'action' => '',
        ]);
        $manager->apply_form_step_result($stepkey, $stepresult);

        $checkoutmanagerdata = $manager->render_overview();
        $data = array_merge($data, $checkoutmanagerdata);
        $data['area'] = 'main';

        return [
            'data' => json_encode($data),
            'jsscript' => '',
            'reloadbody' => false,
            'managerdata' => json_encode($manager->managercache),
        ];
    }

    /**
     * Writes a form-step result into the manager cache and recomputes the
     * checkout validation (mirrors the bookkeeping of check_preprocess).
     *
     * @param string $stepkey
     * @param array $stepresult ['data' => array, 'valid' => bool, 'mandatory' => bool]
     * @return void
     */
    public function apply_form_step_result(string $stepkey, array $stepresult): void {
        $this->managercache['steps'][$stepkey] = $stepresult;

        $classname = self::NAMESPACE_PREFIX . $stepkey;
        if (class_exists($classname)) {
            $iteminstance = new $classname($this->identifier);
            if ($stepresult['valid']) {
                $this->managercache['feedback'] = [
                    'validationmessage' => $iteminstance->get_validation_feedback(),
                ];
            } else {
                $this->managercache['feedback'] = [
                    'errormessage' => $iteminstance->get_error_feedback(),
                ];
            }
        }

        self::get_checkout_validation();
        self::set_cache();
    }

    /**
     * Function to return the stored selected addresses
     *
     * @param int $userid
     *
     * @return array
     *
     */
    public static function return_stored_addresses_for_user(int $userid) {
        $data = self::get_cache($userid);

        return $data["steps"]["addresses"]["data"] ?? [];
    }

    /**
     * Function to return the stored selected addresses
     * @param int $userid
     * @return array
     */
    public static function return_stored_vatnuber_country_code(int $userid): array {
        $taxcountryinformation = [];
        $data = self::get_cache($userid);
        $vatnrcheckerdata = json_decode($data["steps"]["vatnrchecker"]["data"] ?? '');
        if (
            isset($vatnrcheckerdata->vatCodeCountry) &&
            $data["steps"]["vatnrchecker"]['valid']
        ) {
            $explodedvatnrcheckerdata = explode(',', $vatnrcheckerdata->vatCodeCountry);
            $taxcountryinformation = [
                'taxcountrycode' => $explodedvatnrcheckerdata[0] ?? '',
                'vatnumber' => str_replace(
                    $explodedvatnrcheckerdata[0],
                    '',
                    $explodedvatnrcheckerdata[1] ?? ''
                ),
            ];
        }
        return $taxcountryinformation;
    }
}
