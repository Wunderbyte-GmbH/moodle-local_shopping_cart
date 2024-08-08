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

/* eslint-disable no-console */

/*
 * The Cashier module.
 *
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Url from 'core/url';
import {showNotification} from 'local_shopping_cart/notifications';
import ModalForm from 'core_form/modalform';
import {reinit} from 'local_shopping_cart/cart';
import {deleteAllItems} from 'local_shopping_cart/cart';
import {get_string as getString} from 'core/str';

/**
 * Init function.
 * @param {*} userid the user id, 0 means logged-in USER
 */
export const init = (userid = 0) => {

    console.log('run init', userid);

    document.getElementById('checkout-tab').classList.remove('success');

    const buybuttons = document.querySelectorAll('.buy-btn');
    const manualrebookbtn = document.querySelector('#cashiermanualrebook-btn');
    const cartcancelbtn = document.querySelector('#shoppingcart-cancel-btn');

    if (buybuttons) {
        buybuttons.forEach(buybutton => {
            buybutton.addEventListener('click', (e) => confirmPayment(userid, e.target.dataset.paymenttype, ''));
        });
    }

    if (manualrebookbtn) {
        manualrebookbtn.addEventListener('click', (e) => rebookOrderidModal(userid, e.target.dataset.paymenttype));
    }

    if (cartcancelbtn) {
        cartcancelbtn.addEventListener('click', () => {
            deleteAllItems(userid);
            const newurl = Url.relativeUrl("/local/shopping_cart/cashier.php", [], false);
            location.href = newurl;
        });
    }

    const checkoutbutton = document.querySelector('#checkout-btn');

    console.log(checkoutbutton);
    if (checkoutbutton) {
        checkoutbutton.addEventListener('click', function() {

            document.getElementById('checkout-tab').classList.add('success');

            console.log('click');
        });
    }
};

export const confirmPayment = (userid, paymenttype, annotation = '') => {

    Ajax.call([{
        methodname: "local_shopping_cart_confirm_cash_payment",
        args: {
            'userid': userid,
            'paymenttype': paymenttype,
            'annotation': annotation,
        },
        done: function(data) {
            if (data.status === 1) {

                console.log('payment confirmed', data);

                // The function can be called via cashier, or because a user pays via credits.
                // If that's the case, we are not on the cashier site.

                const oncashier = window.location.href.indexOf("cashier.php");

                // If we are not on cashier, we can just redirect.
                if (oncashier < 1) {

                    const identifier = data.identifier;

                    let params = {
                        success: 1,
                        identifier: identifier,
                    };

                    const newurl = Url.relativeUrl("/local/shopping_cart/checkout.php", params, false);

                    location.href = newurl;

                } else {

                    // Set link to right receipt.
                    addPrintIdentifier(data.identifier, userid);

                    document.getElementById('success-tab').classList.add('success');

                    displayPaymentMessage('paymentsuccessful');
                }

            } else {
                console.log('payment denied');
                displayPaymentMessage('paymentdenied', false);
                document.getElementById('success-tab').classList.add('error');
            }
        },
        fail: function(ex) {

            displayPaymentMessage('paymentdenied', false);

            console.log(ex);
        },
    }]);
};

export const validateCart = ($userid) => {
    // eslint-disable-next-line no-alert
    alert($userid);
};

/**
 * Adds parameters to the printbutton.
 * @param {int} identifier
 * @param {int} userid
 */
export const addPrintIdentifier = (identifier, userid) => {
   let printbtn = document.getElementById('printbtn');
   let href = printbtn.getAttribute('href');
   printbtn.setAttribute('href', href + identifier + '&userid=' + userid);
};

/**
 *
 * @param {*} event
 */
export function discountModal(event) {

    // We two parents up, we find the right element with the necessary information.
    const element = event.target.closest('.shopping-cart-item');

    /* Console.log('closest', element); */

    const price = element.dataset.price;
    const itemid = element.dataset.itemid;
    const userid = element.dataset.userid;
    const componentname = element.dataset.component;
    const area = element.dataset.area;

    /* Console.log('discountModal', price, itemid, userid, componentname, 'area ' + area); */

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_add_discount_to_item",
        // Add as many arguments as you need, they will be passed to the form:
        args: {'price': price,
               'itemid': itemid,
               'userid': userid,
               'componentname': componentname,
               'area': area},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('applydiscount', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: element
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {

        /* Const response = e.detail;
        console.log('confirmCancelAndSetCreditModal response: ', response); */

        reinit(-1);
    });

    // Show the form.
    modalForm.show();

}

/**
 * This function first displays the result at the right place.
 * It further uses the notification class to make result even more visible.
 * @param {string} message
 * @param {bool} success
 */
function displayPaymentMessage(message, success = true) {
    let displaymessage = document.querySelector('.payment_message_result');
    if (displaymessage) {
        getString(message, 'local_shopping_cart').then(localizedmessage => {

            displaymessage.innerText = localizedmessage;

            if (success) {
                showNotification(localizedmessage, "info");
            } else {
                showNotification(localizedmessage, "error");
            }
            return;
        }).catch(e => {
            showNotification(`Error: ${e}`, "error");
        });
    }
}

/**
 * Modal to enter OrderID for manual rebookings.
 * @param {int} userid
 * @param {int} identifier
 */
export function rebookOrderidModal(userid, identifier) {

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_cashier_manual_rebook",
        // Add as many arguments as you need, they will be passed to the form:
        args: {
            'userid': userid,
            'identifier': identifier,
        },
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('annotation_rebook_desc', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        // returnFocus: button
    });

    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;

        /* Console.log('rebookOrderidModal response: ', response); */

        // We just add the paidby code to the annotation.
        confirmPayment(userid, 7, `${response.annotation} ${response.paidby}`);
    });

    // Show the form.
    modalForm.show();

}
