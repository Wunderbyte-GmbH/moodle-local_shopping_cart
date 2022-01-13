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

import Ajax from 'core/ajax';


export const buttoninit = (id, component) => {

    // eslint-disable-next-line no-console
    console.log('initialized', id, component);

    // First we get the button and delete the helper-span to secure js loading.
    const addtocartbutton = document.querySelector('#btn-' + component + '-' + id);

    // If we don't find the button, we abort.
    if (!addtocartbutton) {
        return;
    }

    // We remove the backup span element, as we don't need it anymore.
    addtocartbutton.querySelector('.loadJavascript').remove();
    // We show the potentially hidden second span element with the button text.
    const spanlabel = addtocartbutton.querySelector('span');
    if (spanlabel) {
        spanlabel.classList.remove('hidden');
    }

    // Make sure item is not yet in shopping cart. If so, add disabled class.
    const shoppingcart = document.querySelector('#nav-shopping_cart-popover-container');
    if (shoppingcart) {
        const cartitem = shoppingcart.querySelector('#item-' + component + '-' + id);
        if (cartitem) {
            addtocartbutton.classList.add('disabled');
        }
    }
    // Add click eventlistern to oneself.
    addtocartbutton.addEventListener('click', event => {
        // If we find the disabled class, the click event is aborted.
        if (addtocartbutton.classList.contains('disabled')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        addItem(id, component);
    });
};

/**
 * Gets called from mustache template.
 *
 */
 export const init = () => {
    document.querySelectorAll('.shopping-cart-items [id^=item]').forEach(listitem => {
        // eslint-disable-next-line no-console
        console.log(listitem.dataset.expirationdate + " asd a" + listitem.dataset.id);
        setTimer(listitem.dataset.expirationdate, listitem.dataset.id, listitem.dataset.component);
    });
    document.querySelectorAll('.fa-trash-o').forEach(item => {
        addDeleteevent(item);
    });
};

export const deleteItem = (id, component) => {
    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'itemid': id,
            'component': component
        },
        done: function() {
            // eslint-disable-next-line no-console
            console.log(id);
            let item = document.querySelector('#item-' + component + '-' + id);
            if (item) {
                item.remove();
                let itemcount1 = document.getElementById("countbadge");
                let itemcount2 = document.getElementById("itemcount");

                itemcount1.innerHTML = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
                let itemprice = item.dataset.price;
                let total = document.getElementById('totalprice');
                total = total == "undefined" ? 0 : total;
                total.innerHTML -= itemprice;

                // Make sure addtocartbutton active againe once the item is removed from the shopping cart.
                const addtocartbutton = document.querySelector('#btn-' + component + '-' + id);
                if (addtocartbutton) {
                    addtocartbutton.classList.remove('disabled');
                }
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(id, ex);
            let item = document.querySelector('#item-' + component + '-' + id);
            if (item) {
                item.remove();
                let itemcount1 = document.getElementById("countbadge");
                let itemcount2 = document.getElementById("itemcount");
                itemcount1.innerHTML = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
                let itemprice = item.dataset.price;
                let total = document.getElementById('totalprice');
                total = total == "undefined" ? 0 : total;
                total.innerHTML -= itemprice;
            }
        },
    }]);
};

export const addItem = (id, component) => {
    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'component': component,
            'itemid': id
        },
        done: function(data) {
            let html = '<li id="item-' + component + '-' + data.itemid + '" class="clearfix" data-price="'
                    + data.price + '" data-name="' + data.itemname + '" data-component="' + component + '">' +
            '<span class="item-name"><i class="fa fa-futbol-o" aria-hidden="true"></i>' + data.itemname + '</span>' +
            '<span class="item-price pull-right">' + data.price + ' ' + data.currency + '</span><br>' +
           '<span class="item-time pl-3">[<span id="time-item-' + component + '-' + data.itemid + '"></span>]</span>' +
            '<span class="pull-right"><i class="fa fa-trash-o lighter-text"data-id="item-'
                    + component + '-'
                    + data.itemid
                    + '"></i></span>' +
            '</li>';
            let lastElem = document.getElementById('litotalprice');
            lastElem.insertAdjacentHTML('beforeBegin', html);
            document.getElementById("countbadge").innerHTML++;

            const badge = document.getElementById("itemcount");
            badge.innerHTML++;
            badge.classList.remove('hidden');

            let total = document.getElementById('totalprice');
            total.innerHTML = parseInt(total.innerHTML) + parseInt(data.price);
            addDeleteevent(document.querySelector('#item-' + component + '-' + data.itemid + ' .fa-trash-o'));
            setTimer(data.expirationdate, data.itemid, component);

            // Make sure addtocartbutton is disabled once the item is in the shopping cart.
            const addtocartbutton = document.querySelector('#btn-' + component + '-' + data.itemid);
            if (addtocartbutton) {
                addtocartbutton.classList.add('disabled');
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log('error', ex);
        }
    }], true);
};

/**
 * Delete Event.
 * @param {*} item
 */
function addDeleteevent(item) {
    item.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();

        // eslint-disable-next-line no-console
        console.log('delete clicked', item.dataset.id);

        let idarray = item.dataset.id.split('-');

        let id = idarray.pop();
        let component = idarray.pop();
        deleteItem(id, component);
    });
}

/**
 * Start the timer.
 * @param {int} duration
 * @param {int} display
 * @param {int} id
 * @param {string} component
 */
function startTimer(duration, display, id, component) {
    var timer = duration,
                minutes,
                seconds;
    let interval = setInterval(function() {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            timer = 0;
            deleteItem(id, component);
            clearInterval(interval);
        }
    }, 1000);
}

/**
 * Set the timer.
 * @param {int} expirationdate
 * @param {int} id
 * @param {string} component
 */
function setTimer(expirationdate, id, component) {
    let delta = 0;
    let now = Date.now('UTC');
    now = (new Date()).getTime() / 1000;
    delta = (expirationdate - now);
    if (delta < 0) {
        delta = 0;
    }
    let display = document.querySelector('#time-item-' + component + '-' + id);
    if (display) {
        startTimer(delta, display, id, component);
    }
}
