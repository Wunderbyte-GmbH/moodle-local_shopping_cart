<template>
  <button 
    class="btn btn-secondary w-100 mt-0 mb-0 pl-1 pr-1 pt-2 pb-2 wb_shopping_cart booking-button-area"
    :disabled="isItemInCart"
    @click="sendToCart"
  >
    <i 
      class="fa fa-cart-plus" 
      aria-hidden="true"
    />
    <span class="addtocartstring">{{ buttonLabel }}</span>
  </button>
</template>

<script setup>

import { getCurrentInstance, onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex'
import addToCart from '../../composable/addToCart'

const currentInstance = getCurrentInstance()
const itemid = currentInstance.appContext.config.globalProperties.itemid
const item = ref({})
const isItemInCart = ref(false)
const buttonLabel = ref('Add to Cart')

// Load Store and Router
const store = useStore()

onMounted( async () => {
  item.value = await store.dispatch('fetchBookingItem', itemid);
})

const sendToCart = () => {
  store.state.store = addToCart(item.value, store.state.store)
  localStorage.setItem('shopping-cart', JSON.stringify(store.state.store));
}

watch(() => store.state.store, async () =>{
  isItemInCart.value = store.state.store.some(obj => obj.id === item.value.id);
  buttonLabel.value = isItemInCart.value ? 'In Cart' : 'Add to Cart';
},{ deep: true, flush: 'post' })

</script>