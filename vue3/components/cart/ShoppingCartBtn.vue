<template>
  <div>
    <button 
      class="icon-button" 
      @click="showPopup"
    >
      <i 
        class="fa fa-shopping-cart" 
        title="Shopping cart menu" 
        aria-name="Shopping cart menu"
      />
      <span 
        v-if="cartItemsCount > 0" 
        class="cart-count"
      >
        {{ cartItemsCount }}
      </span>
    </button>
    <transition
      name="popup-card" 
      mode="out-in"
    >
      <div 
        v-if="popupVisible" 
        class="popup card"
      >
        <div class="card-body">
          <h5 class="card-title">
            <i class="fa fa-shopping-cart mr-2" />
            Shopping Cart
            <span 
              v-if="remainingTime > 0" 
              class="timer"
            >
              {{ formatTime(remainingTime) }}
            </span>
          </h5>
          <ul 
            class="cart-items" 
            style="padding-inline-start: 0rem;"
          >
            <transition-group 
              name="cart-item" 
              tag="div"
            >
              <li 
                v-if="cartItems.length === 0"
                class="list-group-item"
              >
                Nothing inside the cart yet
              </li>
              <li 
                v-else
                v-for="item in cartItems" 
                :key="item.id" 
                class="list-group-item"
              >
                <span>{{ item.text }} - <b>${{ item.price }}</b></span>
                <i
                  class="fa fa-trash-alt"
                  title="Remove from cart"
                  style="cursor: pointer; margin-left: 10px;"
                  @click="sendFromCart(item.id)"
                />
              </li>
            </transition-group>
          </ul>
        </div>

        <div class="cart-total mt-2" style="text-align: right; font-weight: bold;">
          Total: ${{ cartTotalPice }}
        </div>

        <div class="checkout-button mt-3">
          <a 
            v-if="cartItemsCount > 0"
            href="/local/shopping_cart/checkout.php#/shoppingcart/edit" 
            class="btn btn-primary"
          >
            Proceed to Checkout
          </a>
          <a 
            v-else
            class="btn btn-primary disabled"
          >
            Nothing to Checkout
          </a>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';
import removeFromCart from '../../composable/removeFromCart';

const store = useStore();
const popupVisible = ref(false);
const cartItems = ref([]);
const cartItemsCount = ref(0);
const cartTotalPice = ref(0);
const timerDuration = 15 * 60 * 1000; // 15 minutes in milliseconds

const showPopup = () => {
  popupVisible.value = !popupVisible.value;
};

const calculateTotal = () => {
  cartTotalPice.value = cartItems.value.reduce((total, item) => total + parseFloat(item.price), 0);
};

watch(() => store.state.store, async () => {
  cartItems.value = store.state.store;
  cartItemsCount.value = cartItems.value.length;
  calculateTotal();
}, { deep: true, flush: 'post' });

const sendFromCart = (itemId) => {
  store.state.store = removeFromCart(itemId, store.state.store);
  localStorage.setItem('shopping-cart', JSON.stringify(store.state.store));
};

onMounted( async () => {
  const localStorageCart = JSON.parse(localStorage.getItem('shopping-cart'));
  if (localStorageCart != null) {
      store.state.store = localStorageCart
  }
  setInterval(() => {
    remainingTime.value = calculateRemainingTime();
  }, 1000); 
})

const calculateRemainingTime = () => {
  const startTime = parseInt(localStorage.getItem('shopping-cart-timer'));
  if (!isNaN(startTime)) {
    const currentTime = Date.now();
    const elapsed = currentTime - startTime;
    const remaining = timerDuration - elapsed;
    return Math.max(remaining, 0);
  }
  return 0;
};

const remainingTime = ref(calculateRemainingTime());

watch(popupVisible, () => {
  remainingTime.value = calculateRemainingTime();
});

const formatTime = (milliseconds) => {
  const minutes = Math.floor(milliseconds / (60 * 1000));
  const seconds = Math.floor((milliseconds % (60 * 1000)) / 1000);
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};


</script>

<style scoped>
  .icon-button {
    background: none;
    border: none;
    cursor: pointer;
  }

  .popup {
    position: absolute;
    background-color: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    padding: 10px;
    width: 400px; /* Set the width */
    top: 100%; /* Position below the cart icon */
    transform: translateX(-90%);
  }


  .cart-items {
    max-height: 200px; /* Set a max height for the list */
    overflow-y: auto; /* Add a scrollbar if the list is too long */
    list-style: none; /* Remove default list styling */
    padding: 0;
  }

  .list-group-item {
    border: none;
    border-bottom: 1px dotted #ccc; /* Add dotted border to each list item */
    padding: 10px; /* Add padding for better spacing */
    display: flex;
    justify-content: space-between; /* Align text and trash icon to opposite ends */
  }

  .cart-item-enter-active, .cart-item-leave-active {
    transition: all 0.5s ease;
  }

  .cart-item-leave-to,
  .cart-item-enter-from {
    opacity: 0;
    transform: translateY(30px);
  }

  .popup-card-enter-active, .popup-card-leave-active {
    transition: all 0.3s ease;
  }

  .popup-card-leave-to,
  .popup-card-enter-from {
    opacity: 0;
    transform: translateY(-30px); /* Adjust the value to control the vertical animation */
    transform: translateX(-90%);
  }

  .cart-count {
    padding: 2px;
    border-radius: 2px;
    background-color: #ca3120;
    color: #fff;
    font-size: 11px;
    line-height: 11px;
    position: relative;
    top: -8px;
    right: 2px;
  }
  .cart-count-animated {
    transition: opacity 0.5s, transform 0.5s;
  }

  .timer {
    font-size: 12px;
    margin-left: 10px;
    color: #888;
  }
</style>