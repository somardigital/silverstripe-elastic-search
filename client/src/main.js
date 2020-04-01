import Vue from "vue"
import Search from "./Search"

Vue.config.productionTip = false

new Vue({
  render: h => h(Search),
}).$mount("#search-app")
