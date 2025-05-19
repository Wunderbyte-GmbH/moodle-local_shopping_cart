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
    EDITADDRESSBUTTON: '.shopping-cart-edit-selected-address',
    DELETESELECTEDADDRESS: '.shopping-cart-delete-selected-address',
};

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
    setEditEventListeners();
};

/**
 * Show Modal.
 */
export function setDeletionEventListeners() {
    const deleteAddressButtons = document.querySelectorAll(SELECTORS.DELETESELECTEDADDRESS);
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
 */
export function setEditEventListeners() {
    const editAddressButtons = document.querySelectorAll(SELECTORS.EDITADDRESSBUTTON);
    if (editAddressButtons) {
        editAddressButtons.forEach(editAddressButton => {
            editAddressButton.addEventListener('click', e => {
                let selectedRadio = document.querySelector(
                    `input[name^="selectedaddress_"]:checked`
                );
                e.preventDefault();
                editAddressButton.setAttribute('data-address-id', selectedRadio.value);
                newAddressModal(editAddressButton);
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
    const selectedradio = document.querySelector(
        `input[name^="selectedaddress_"]:checked`
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
    // Detect if we are editing an existing address via the button's dataset or other relevant data.
    const id = button.dataset.addressId ?? 0;

    // Set the save button text based on whether the address is being edited or added.
    const saveButtonText = id > 0
        ? getString('addresses:saveaddress:submit', 'local_shopping_cart') // Change "Add Address" to "Save Address" for edits.
        : getString('addresses:newaddress:submit', 'local_shopping_cart'); // Default text for adding.

    const modalForm = new ModalForm({
        // Name of the class where the form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_new_address",
        // Pass arguments to indicate the state of the modal (new or edit):
        args: {id},
        // Configure the modal dialog with the updated save button text:
        modalConfig: {title: getString('addresses:newaddress', 'local_shopping_cart')},
        // DOM element that should get focus after the modal dialog is closed:
        returnFocus: button,
        saveButtonText: saveButtonText
    });

    // Listen to form submission events.
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;

        // Determine the key to use based on whether the response is for a new or updated address.
        const stringKey = response.isnew
            ? 'addresses:newaddress:saved' // String for new address saved.
            : 'addresses:newaddress:updated'; // String for updated address.

        // Get the appropriate string and show the notification.
        getString(stringKey, 'local_shopping_cart')
            .then(str => {
                showNotification(str, 'info');
                return null;
            })
            .catch((error) => {
                console.log(error); // eslint-disable-line no-console
            });

        // Redraw the rendered addresses list based on the server response.
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
