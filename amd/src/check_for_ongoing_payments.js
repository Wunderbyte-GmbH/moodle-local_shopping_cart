import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';
import Ajax from 'core/ajax';

/**
 *
 * @param {*} userid
 */
export const init = async (userid) => {

  const modal = await ModalFactory.create({
        body: await Templates.render('local_shopping_cart/checkongoing', {})
  });

  Ajax.call([{
    methodname: "local_shopping_card_check_for_ongoing_payment",
    args: {
        userid,
    },
    done: function(data) {
        modal.hide();
        if (data.success !== true) {
            window.href = data.url;
        }
    }
    }]);

};