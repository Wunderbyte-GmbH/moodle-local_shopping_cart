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
import DynamicForm from 'core_form/dynamicform';

import {get_string as getString} from 'core/str';
import Templates from 'core/templates';

const SELECTORS = {
    ADDRESSRENDERCONTAINER: '#addressestemplatespace',
    NEWADDRESSBUTTON: '.shopping-cart-new-address',
    EDITADDRESSBUTTON: '.shopping-cart-edit-selected-address',
    DELETESELECTEDADDRESS: '.shopping-cart-delete-selected-address',
    INLINEFORMCONTAINER: '[id^="shopping-cart-inline-address-form-"]',
};

let inlineAddressForms = {};

const FORMCLASS = 'local_shopping_cart\\form\\modal_new_address';

export const init = () => {
    if (document.body.dataset.shoppingCartAddressInit === 'true') {
        return;
    }

    document.body.dataset.shoppingCartAddressInit = 'true';

    document.addEventListener('click', (event) => {
        const newbutton = event.target.closest(SELECTORS.NEWADDRESSBUTTON);
        if (newbutton) {
            event.preventDefault();
            newAddressModal(newbutton, 0);
            return;
        }

        const editbutton = event.target.closest(SELECTORS.EDITADDRESSBUTTON);
        if (editbutton) {
            event.preventDefault();
            const addresskey = editbutton.dataset.addresskey || 'billing';
            const selectedradio = document.querySelector(`input[name="selectedaddress_${addresskey}"]:checked`);
            if (!selectedradio) {
                getString('addresses:delete:noaddressselected', 'local_shopping_cart').then(str => {
                    showNotification(str, 'warning');
                    return;
                }).catch(
                    // eslint-disable-next-line no-console
                    console.error
                );
                return;
            }
            newAddressModal(editbutton, Number(selectedradio.value || 0));
            return;
        }

        const deletebutton = event.target.closest(SELECTORS.DELETESELECTEDADDRESS);
        if (deletebutton) {
            handleAddressDeletion(event, deletebutton);
        }
    });

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
        deleteAddress(response.status ?? 0);
        redrawRenderedAddresses(response.templatedata || {}, null, button.dataset.addresskey || 'billing');
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
 * Open inline address form in create/edit mode.
 * @param {HTMLElement} button
 * @param {Number} id
 */
export function newAddressModal(button, id = 0) {
    const preferredAddressKey = button.dataset.addresskey || 'billing';
    const container = document.querySelector(`#shopping-cart-inline-address-form-${preferredAddressKey}`);
    if (!container) {
        return;
    }

    mountInlineAddressForm(container, id, preferredAddressKey);
}

/**
 * Mount or remount one inline address dynamic form.
 * @param {HTMLElement} container
 * @param {Number} id
 * @param {String} addressKey
 */
function mountInlineAddressForm(container, id = 0, addressKey = 'billing') {
    inlineAddressForms[addressKey] = new DynamicForm(container, FORMCLASS, {id});

    inlineAddressForms[addressKey].addEventListener(inlineAddressForms[addressKey].events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        const stringKey = response.isnew ? 'addresses:newaddress:saved' : 'addresses:newaddress:updated';

        getString(stringKey, 'local_shopping_cart')
            .then(str => {
                showNotification(str, 'info');
                return null;
            })
            .catch((error) => {
                // eslint-disable-next-line no-console
                console.log(error);
            });

        redrawRenderedAddresses(response.templatedata, response.newaddressid, addressKey);
    });

    inlineAddressForms[addressKey].load({id});
}

/**
 * Re-Renders the address list with the newly returned data (most possible containing new saved addresses)
 * @param {Array} data data from addresses::get_template_render_data needed for rendering the address.mustache template
 * @param {Number|null} newAddressId newly created/updated address id
 * @param {String|null} preferredAddressKey preferred key to auto-select (e.g. billing)
 */
function redrawRenderedAddresses(data, newAddressId = null, preferredAddressKey = null) {
    // On the checkout page the addresses step is a dynamic form: reload it
    // instead of re-rendering the legacy template (address.php still uses it).
    const stepformcontainer = document.querySelector('[data-stepkey="addresses"]');
    if (stepformcontainer) {
        // Replace the inline-form containers with fresh clones: the legacy
        // redraw recreated them (dropping the DynamicForm listeners), the
        // form reload does not - without this, remounting stacks a second
        // DynamicForm on the same container.
        document.querySelectorAll(SELECTORS.INLINEFORMCONTAINER).forEach(container => {
            container.parentNode.replaceChild(container.cloneNode(false), container);
        });
        inlineAddressForms = {};
        document.dispatchEvent(new CustomEvent('local_shopping_cart/reloadAddressStep', {
            detail: {
                newaddressid: newAddressId,
                addresskey: preferredAddressKey || 'billing',
            },
        }));
        return;
    }
    Templates.renderForPromise('local_shopping_cart/address', data).then(({html, js}) => {
        // The rendered template root is the container itself, so replace the
        // whole node to avoid nesting duplicate ids on every redraw.
        Templates.replaceNode(document.querySelector(SELECTORS.ADDRESSRENDERCONTAINER), html, js);
        inlineAddressForms = {};

        if (newAddressId) {
            let selectedRadio = null;
            if (preferredAddressKey) {
                selectedRadio = document.querySelector(
                    `input[name="selectedaddress_${preferredAddressKey}"][value="${newAddressId}"]`
                );
            }
            if (!selectedRadio) {
                selectedRadio = document.querySelector(
                    `input[name^="selectedaddress_"][value="${newAddressId}"]`
                );
            }
            if (selectedRadio) {
                selectedRadio.checked = true;
                selectedRadio.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }

        const event = new CustomEvent('local_shopping_cart/addressesRedrawn', {});
        document.dispatchEvent(event);
        return null;
    }).catch((e) => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}
