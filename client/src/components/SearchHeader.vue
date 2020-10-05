<template>
  <div class="search__header">
    <h2 v-if="searchedKeyword && !loadingResults">{{ resultsCount }} results found for ‘{{ searchedKeyword }}’</h2>
    <h2 v-else-if="keyword">Searching for ‘{{ keyword }}’ ...</h2>
    <h2 v-else>{{ config.labels.title }}</h2>
    <div class="search__keyword">
      <input
        type="search"
        class="search__input"
        v-model="keyword"
        @input="onKeywordChange"
        :placeholder="searchPlaceholder"
      />
      <i class="material-icons text-primary">search</i>
    </div>
    <a
      v-if="config.secondarySearch"
      :href="secondarySearchURL"
      class="search__secondary-link btn btn-outline-primary btn-round"
      >{{ config.secondarySearch.title }}</a
    >

    <div class="search__filters">
      <h3 class="search__hint">{{ config.labels.filtersHint }}</h3>
      <div class="row">
        <div v-for="(filterConfig, i) in config.filters" class="col-md-6" :key="filterConfig.name">
          <multiselect
            class="multiselect--multiple"
            :key="i"
            v-model="filters[filterConfig.name]"
            track-by="value"
            label="name"
            :placeholder="filterConfig.placeholder"
            :options="filterConfig.options"
            :multiple="true"
            :searchable="false"
            @keydown.native.tab="keyTabDown($event, $refs.filterSelect[i])"
            @keydown.native.up="keyArrowUp($refs.filterSelect[i])"
            @keydown.native.down="keyArrowDown($refs.filterSelect[i])"
            @input="onFilterChange"
            @close="onCloseFilterSelect"
            @open="addMultiSelectOverlay($refs.filterSelect[i])"
            ref="filterSelect"
          >
            <template slot="tag" slot-scope="{ option, remove }">
              <span class="multiselect__tag">
                {{ option.name }}
                <button class="multiselect__tag-remove" @click="remove(option)">
                  <i class="material-icons">close</i>
                </button>
              </span>
            </template>
          </multiselect>
        </div>
        <div class="col-md-6" v-if="config.date">
          <multiselect
            v-model="dateFilter"
            track-by="value"
            label="name"
            placeholder="By date"
            :options="config.date.options"
            :searchable="false"
            @keydown.native.tab="keyTabDown($event, $refs.dateSelect)"
            @keydown.native.up="keyArrowUp($refs.dateSelect)"
            @keydown.native.down="keyArrowDown($refs.dateSelect)"
            @input="onDateFilterChange"
            @close="onCloseDateSelect"
            @open="addMultiSelectOverlay($refs.dateSelect)"
            ref="dateSelect"
          >
          </multiselect>

          <div v-if="dateFilter && dateFilter.value == 'range'" class="search__dates row">
            <div class="col-sm-4">
              <label class="search__date search__date-from">
                Date From
                <input type="date" v-model="dateFrom" @input="search" />
              </label>
            </div>
            <div class="col-sm-4">
              <label class="search__date search__date-to">
                Date To
                <input type="date" v-model="dateTo" @input="search" />
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { searchConfig, debounce, buildSearchQueryString } from "@/utils"
import Multiselect from "vue-multiselect"

export default {
  props: {
    resultsCount: Number,
    loadingResults: Boolean,
  },

  components: {
    Multiselect,
  },

  data() {
    return {
      keyword: "",
      searchedKeyword: "",
      filters: {},
      dateFilter: null,
      dateFrom: null,
      dateTo: null,
      config: searchConfig,
    }
  },

  mounted() {
    this.$nextTick().then(() => {
      this.$el.querySelectorAll(".multiselect").forEach(item => {
        item.setAttribute("role", "button")
        item.setAttribute("aria-pressed", "false")
        item.setAttribute("aria-label", item.querySelector("span.multiselect__placeholder").innerHTML.trim())
      })
    })
  },

  computed: {
    searchParams: function() {
      const filters = {}
      this.config.filters.forEach(filter => {
        if (this.filters[filter.name]) {
          filters[filter.name] = this.filters[filter.name].map(option => option.value)
        }
      })

      return {
        q: this.keyword,
        sort: this.dateFilter && ["asc", "desc"].includes(this.dateFilter.value) ? this.dateFilter.value : "",
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
        ...filters,
      }
    },
    url: function() {
      return window.href
    },
    secondarySearchURL: function() {
      if (!this.config.secondarySearch) {
        return ""
      }

      return `${this.config.secondarySearch.url}${buildSearchQueryString(this.searchParams)}`
    },
    searchPlaceholder: function() {
      return this.config.placeholder || ""
    },
  },

  watch: {
    loadingResults: function(isLoading) {
      if (!isLoading) {
        this.searchedKeyword = this.keyword
      }
    },
  },

  created() {
    this.initFilters()
    this.search()

    // update search on back/forward button
    const _this = this
    window.onpopstate = function() {
      _this.initFilters()
      _this.search(false)
    }
  },

  methods: {
    keyTabDown(e, el) {
      e.preventDefault()
      e.stopImmediatePropagation()
      e.stopPropagation()

      if (e.shiftKey) {
        const n = el.curPointer
        if (n == 0) {
          el.pointer = el.options.length - 1
          el.$refs.list.scrollTop = el.$refs.list.scrollHeight
        } else {
          el.pointer--
        }

        el.curPointer = el.pointer
      } else {
        const n = el.curPointer
        if (n == el.options.length - 1) {
          el.pointer = 0
          el.$refs.list.scrollTop = 0
        } else {
          el.pointer++
        }

        el.curPointer = el.pointer
      }

      const cur = el.$refs.list.children[0].children[el.curPointer]
      if (el.$refs.list.clientHeight - el.$refs.list.scrollTop <= cur.offsetTop) {
        el.$refs.list.scrollTop = cur.offsetTop
      } else if (cur.offsetTop - el.$refs.list.scrollTop < 0 ) {
        el.$refs.list.scrollTop = cur.offsetTop
      }
    },
    keyArrowUp(el) {
      const n = el.curPointer
      if (n == 0) {
        el.pointer = el.options.length - 1
        el.$refs.list.scrollTop = el.$refs.list.scrollHeight
      }

      el.curPointer = el.pointer
    },
    keyArrowDown(el) {
      const n = el.curPointer
      if (n == el.options.length - 1) {
        el.pointer = 0
        el.$refs.list.scrollTop = 0
      }

      el.curPointer = el.pointer
    },
    initFilters() {
      const uri = window.location.search.substring(1)
      const params = new URLSearchParams(decodeURI(uri))

      this.searchedKeyword = this.keyword = params.get("q")

      const activeFilters = {}
      this.config.filters.forEach(filter => {
        activeFilters[filter.name] = filter.options.filter(option => {
          return params.getAll(`${filter.name}[]`).includes(option.value)
        })
      })
      this.filters = activeFilters

      if (this.config.date) {
        this.dateFilter = this.config.date.options.find(option => params.get("sort") == option.value)

        if (params.get("dateFrom") || params.get("dateTo")) {
          this.dateFilter = this.config.date.options.find(option => "range" == option.value)
          this.dateFrom = params.get("dateFrom")
          this.dateTo = params.get("dateTo")
        }
      }
    },

    onKeywordChange() {
      this.debouncedSearch()
    },

    onFilterChange() {
      this.search()
    },

    onDateFilterChange() {
      this.dateFrom = this.dateTo = null

      if (this.dateFilter && this.dateFilter.value != "range") {
        this.search()
      }
    },

    debouncedSearch: debounce(function() {
      this.search()
    }, 500),

    search(updateURL = true) {
      if (!this.config.allowEmptyKeyword && !this.keyword) {
        return
      }

      if (updateURL) {
        const pagePath = window.location.pathname.replace(/\/$/, "")
        window.history.pushState({}, "", `${pagePath}/${buildSearchQueryString(this.searchParams)}`)
      }

      this.$emit("search", this.searchParams)
    },

    /**
     * START: Fix for Safari on iOS (https://github.com/shentao/vue-multiselect/issues/709)
     */
    addMultiSelectOverlay(el) {
      el.curPointer = el.pointer
      el.$el.setAttribute("aria-pressed", "true")
      const body = document.querySelector("body")
      const overlay = document.createElement("div")
      window.removeEventListener("keydown", this.keydownHandler)
      window.addEventListener("keydown", this.keydownHandler)

      overlay.classList.add("multiselect__overlay")

      overlay.style.position = "fixed"
      overlay.style.top = 0
      overlay.style.left = 0
      overlay.style.width = "100%"
      overlay.style.height = "100%"
      overlay.style.zIndex = 9999

      body.appendChild(overlay)

      overlay.addEventListener("click", () => {
        if (this.$refs.filterSelect.length) {
          this.$refs.filterSelect[0].deactivate()
        }

        if (Array.isArray(this.$refs.dateSelect) && this.$refs.dateSelect.length) {
          this.$refs.dateSelect[0].deactivate()
        } else {
          this.$refs.dateSelect.deactivate()
        }

        this.removeMultiSelectOverlay()
      })

      this.$el.querySelectorAll(".multiselect__content-wrapper").forEach(item => {
        item.setAttribute("tabindex", 0)
      })

      this.$el.querySelectorAll(".multiselect__option").forEach(item => {
        item.setAttribute("tabindex", 0)
        item.setAttribute("role", "button")
        item.setAttribute("aria-pressed", "false")
      })
    },

    removeMultiSelectOverlay() {
      window.removeEventListener("keydown", this.keydownHandler)
      const overlay = document.querySelector(".multiselect__overlay")

      if (overlay) {
        overlay.remove()
      }
    },

    onCloseFilterSelect() {
      if (this.$refs.filterSelect.length) {
        this.$refs.filterSelect[0].deactivate()
        this.$refs.filterSelect.forEach(filter => {
          filter.$el.setAttribute("aria-pressed", "false")
        })
      }

      this.removeMultiSelectOverlay()
    },

    onCloseDateSelect() {
      if (Array.isArray(this.$refs.dateSelect) && this.$refs.dateSelect.length) {
        this.$refs.dateSelect[0].deactivate()
        this.$refs.dateSelect[0].$el.setAttribute("aria-pressed", "false")
      } else {
        this.$refs.dateSelect.deactivate()
        this.$refs.dateSelect.$el.setAttribute("aria-pressed", "false")
      }

      this.removeMultiSelectOverlay()
    },
    /**
     * END: Fix for Safari on iOS
     */
  },
}
</script>

<style lang="scss" scoped>
.multiselect {
  margin-bottom: 10px;
}
.search {
  &__header {
    h2 {
      margin-bottom: 18px;
      @media (min-width: 768px) {
        margin-right: 240px;
      }
    }
  }
  &__keyword {
    margin-bottom: 20px;
    font-size: 1rem;
    position: relative;

    .material-icons {
      font-size: 32px;
      position: absolute;
      right: 8px;
      top: calc(50% - 16px);
    }
  }

  &__input {
    padding-right: 48px;
  }

  &__filters {
    margin-bottom: 20px;
  }

  &__date {
    width: 100%;
  }

  &__secondary-link {
    margin-bottom: 20px;

    @media (min-width: 768px) {
      position: absolute;
      right: 0;
      top: 0;
    }
  }
}
</style>
