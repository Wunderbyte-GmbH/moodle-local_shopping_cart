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

/*
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {showNotification} from 'local_shopping_cart/notifications';
import ModalForm from 'core_form/modalform';

import {get_string as getString} from 'core/str';
import Templates from 'core/templates';

const SELECTORS = {
    ADDRESSRENDERCONTAINER: '#addressestemplatespace',
    NEWADDRESSBUTTON: '.shopping-cart-new-address',
};

const DELETESELECTEDADDRESS = '.shopping-cart-delete-selected-address';

export const init = () => {
    const buttons = document.querySelectorAll(SELECTORS.NEWADDRESSBUTTON);
    if (buttons) {
        buttons.forEach(newAddressButton => {
            newAddressButton.addEventListener('click', e => {
                e.preventDefault();
                newAddressModal(newAddressButton);
            });
        });
    }
    setDeletionEventListeners();
};

/**
 * Show Modal.
 */
export function setDeletionEventListeners() {
    const deleteAddressButtons = document.querySelectorAll(DELETESELECTEDADDRESS);
    if (deleteAddressButtons) {
        deleteAddressButtons.forEach(deleteAddressButton => {
            deleteAddressButton.addEventListener('click', e => {
                handleAddressDeletion(e, deleteAddressButton);
            });
        });
    }
}

/**
 * Show Modal.
 * @param {Event} event
 * @param {HTMLElement} deleteAddressButton
 */
export function handleAddressDeletion(event, deleteAddressButton) {
    event.preventDefault();
    const addressKey = deleteAddressButton.dataset.addresskey;
    const selectedradio = document.querySelector(
        `input[name="selectedaddress_${addressKey}"]:checked`
    );

    if (selectedradio) {
        const addressId = selectedradio.value;
        confirmAndDeleteAddress(addressId, deleteAddressButton);
    } else {
        getString('addresses:delete:noaddressselected', 'local_shopping_cart').then(str => {
            showNotification(str, 'warning');
            return;
        }).catch(
            // eslint-disable-next-line no-console
            console.error
        );
    }
}

/**
 * Show a confirmation modal and trigger the address deletion process.
 * @param {string} addressId
 * @param {string} button
 */
function confirmAndDeleteAddress(addressId, button) {

    const modalForm = new ModalForm({
        formClass: "local_shopping_cart\\form\\delete_user_address",
        args: {addressid: addressId},
        modalConfig: {
            title: getString('addresses:delete:selected', 'local_shopping_cart'),
        },
        returnFocus: button,
        saveButtonText: getString('addresses:delete:submit', 'local_shopping_cart')
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        deselectAddressCheckbox(button.dataset.addresskey);
        const response = e.detail;
        deleteAddress(response);
        redrawRenderedAddresses(response.templatedata);
    });

    modalForm.show();
    return;
}

/**
 * @param {String} addressType
 */
function deselectAddressCheckbox(addressType) {
    const selectedRadio = document.querySelector(`input[name="selectedaddress_${addressType}"]:checked`);
    if (selectedRadio) {
        selectedRadio.checked = false;
        const event = new Event('change', {bubbles: true});
        selectedRadio.dispatchEvent(event);
    }
}

/**
 * Trigger the address deletion via a web service.
 * @param {string} response
 */
function deleteAddress(response) {
    if (response == 1) {

        getString('addresses:delete:success', 'local_shopping_cart').then(successMessage => {
            showNotification(successMessage, 'success');
            return;
        }).catch(
            // eslint-disable-next-line no-console
            console.error
        );
    } else {
        getString('addresses:delete:error', 'local_shopping_cart').then(successMessage => {
            showNotification(successMessage, 'error');
            return;
        }).catch(
            // eslint-disable-next-line no-console
            console.error
        );
    }
}

/**
 * Show Modal.
 * @param {htmlElement} button
 */
export function newAddressModal(button) {
    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_new_address",
        // Add as many arguments as you need, they will be passed to the form:
        args: {},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('addresses:newaddress', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button,
        saveButtonText: getString('addresses:newaddress:submit', 'local_shopping_cart')
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        getString('addresses:newaddress:saved', 'local_shopping_cart').then(str => {
            showNotification(str, 'info');
            return null;
        }).catch((e) => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
        redrawRenderedAddresses(response.templatedata);
    });

    modalForm.show();
}

/**
 * Re-Renders the address list with the newly returned data (most possible containing new saved addresses)
 * @param {Array} data data from addresses::get_template_render_data needed for rendering the address.mustache template
 */
function redrawRenderedAddresses(data) {
    Templates.renderForPromise('local_shopping_cart/address', data).then(({html, js}) => {
        Templates.replaceNodeContents(document.querySelector(SELECTORS.ADDRESSRENDERCONTAINER), html, js);
        const event = new CustomEvent('local_shopping_cart/addressesRedrawn', {});
        document.dispatchEvent(event);
        return null;
    }).catch((e) => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}
