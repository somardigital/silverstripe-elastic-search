<template>
  <div class="search__header">
    <div class="search__heading">
      <div class="search__heading-inner">
        <search-heading class="search__title" v-html="title" />
        <span class="search__subtitle" v-if="subtitle" v-html="subtitle" />
      </div>
    </div>
    <div class="search__keyword">
      <div class="search__keyword-inner">
        <input
          type="search"
          class="search__input"
          v-model="keyword"
          @input="onKeywordChange"
          :placeholder="searchPlaceholder"
        />
        <i v-if="config.icons == 'material'" aria-hidden="true" class="material-icons text-primary">search</i>
      </div>
    </div>
    <a
      v-if="config.secondarySearch"
      :href="secondarySearchURL"
      class="search__secondary-link btn btn-outline-primary btn-round"
      >{{ config.secondarySearch.title }}</a
    >
    <div class="search__filters">
      <div class="search__filters-inner">
        <h3 v-if="config.labels.filtersHint" class="search__hint">{{ config.labels.filtersHint }}</h3>
        <div class="row search__filters-row">
          <div
            v-for="(filterConfig, i) in config.filters"
            :class="['col-md-6', `col-xl-${filterConfig.columns}`, 'search__filter']"
            :key="filterConfig.name"
          >
            <label v-if="filterConfig.label" class="search__filter-label">{{ filterConfig.label }}</label>

            <template v-if="filterConfig.showInline">
              <ul class="inline-filter">
                <li v-for="{ name, value } in filterConfig.options" :key="name" class="inline-filter__option">
                  <button
                    class="inline-filter__button"
                    :class="{ 'inline-filter__button--active': isFilterOptionActive(filterConfig.name, value) }"
                    @click="changeFilter(filterConfig.name, name, value)"
                    :data-text="name"
                  >
                    {{ name }}
                  </button>
                </li>
              </ul>
            </template>

            <template v-else>
              <multiselect
                :class="[{ 'multiselect--multiple': filterConfig.multiple }, `filter-${filterConfig.name}`]"
                :key="i"
                v-model="filters[filterConfig.name]"
                track-by="value"
                label="name"
                role="button"
                aria-expanded="false"
                :aria-controls="`multi-filter-${filterConfig.name}`"
                :max-height="400"
                :placeholder="filterConfig.placeholder"
                :options="filterConfig.options"
                :multiple="filterConfig.multiple"
                :searchable="!!filterConfig.searchable"
                :aria-label="filterConfig.label || filterConfig.placeholder"
                @keydown.native.space="keySpaceDown($event, $refs[filterConfig.name][0])"
                @keydown.native.tab="keyTabDown($event, $refs[filterConfig.name][0])"
                @keydown.native.up="keyArrowUp($refs[filterConfig.name][0])"
                @keydown.native.down="keyArrowDown($refs[filterConfig.name][0])"
                @input="onFilterChange(filterConfig.name)"
                @close="onCloseFilterSelect($refs[filterConfig.name][0])"
                @open="addMultiSelectOverlay($refs[filterConfig.name][0])"
                :ref="filterConfig.name"
              >
                <template slot="clear" v-if="filterConfig.iconClass">
                  <i class="multiselect__icon" :class="filterConfig.iconClass" aria-hidden="true"></i>
                </template>

                <template slot="caret" v-if="config.caretIconClass">
                  <i class="multiselect__caret-icon" :class="config.caretIconClass" aria-hidden="true"></i>
                </template>

                <template slot="tag" slot-scope="{ option, remove }">
                  <span class="multiselect__tag">
                    {{ option.name }}
                    <button class="multiselect__tag-remove" @click="remove(option)">
                      <i v-if="config.icons == 'material'" class="material-icons">close</i>
                      <span v-else>x</span>
                    </button>
                  </span>
                </template>
              </multiselect>

              <div
                v-if="filterConfig.name == 'date' && filters.date && filters.date.value == 'range'"
                class="search__dates row"
              >
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
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { searchConfig, debounce, buildSearchQueryString, escapeRegExp } from "@/utils"
import Multiselect from "vue-multiselect"

const SearchHeading = {
  render(h) {
    return h(`h${searchConfig.headingLevel}`, this.$slots.default)
  },
}

export default {
  props: {
    resultsCount: Number,
    loadingResults: Boolean,
  },

  components: {
    Multiselect,
    SearchHeading,
  },

  data() {
    return {
      keyword: "",
      searchedKeyword: "",
      filters: {},
      dateFrom: null,
      dateTo: null,
      config: searchConfig,
    }
  },

  mounted() {
    this.$nextTick().then(() => {
      this.$el.querySelectorAll(".multiselect").forEach(item => {
        const id = item.getAttribute("aria-controls")
        item.querySelector(".multiselect__content-wrapper").id = id
      })
    })
  },

  computed: {
    searchParams: function() {
      const filters = {}
      this.config.filters.forEach(filter => {
        let filterValues = this.filters[filter.name]
        if (filterValues) {
          // in case of non-multiple filters
          if (!Array.isArray(filterValues)) {
            filterValues = [filterValues]
          }

          filters[filter.name] = filterValues.map(option => option.value)
        }
      })

      const dateFilter = filters.date[0] || null
      delete filters.date

      return {
        q: this.keyword,
        sort: dateFilter && ["asc", "desc"].includes(dateFilter) ? dateFilter : "",
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

    title() {
      let title = this.config.labels.title

      if (this.searchedKeyword && !this.loadingResults) {
        title = this.config.labels.titleFound
      } else if (this.keyword) {
        title = this.config.labels.titleSearching
      }

      return this.replacePlaceholders(title)
    },

    subtitle() {
      if (this.config.labels.subtitle && this.searchedKeyword && !this.loadingResults) {
        return this.replacePlaceholders(this.config.labels.subtitle)
      }

      return "&nbsp;"
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
    keySpaceDown(e, el) {
      e.preventDefault()
      e.stopImmediatePropagation()
      e.stopPropagation()
      const cur = el.$refs.list.children[0].children[el.curPointer].querySelector(".multiselect__option")
      cur.dispatchEvent(new Event("click"))
    },

    keyTabDown(e, el) {
      if (el.isOpen) {
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
        } else if (cur.offsetTop - el.$refs.list.scrollTop < 0) {
          el.$refs.list.scrollTop = cur.offsetTop
        }
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

        if (filter.default && !activeFilters[filter.name].length) {
          activeFilters[filter.name] = filter.options.find(option => option.value == filter.default)
        }
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

    onFilterChange(filter) {
      if (filter == "date") {
        this.onDateFilterChange()
      } else {
        this.search()
      }
    },

    onDateFilterChange() {
      this.dateFrom = this.dateTo = null

      if (this.filters.date && this.filters.date.value != "range") {
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

    changeFilter(filter, name, value) {
      this.filters[filter] = { name, value }
      this.onFilterChange()
    },

    isFilterOptionActive(filter, value) {
      const filterValues = Array.isArray(this.filters[filter]) ? this.filters[filter] : [this.filters[filter]]

      return !!filterValues.find(filterValue => filterValue.value == value)
    },

    replacePlaceholders(string) {
      const replacements = {
        "[searchedKeyword]": this.searchedKeyword,
        "[keyword]": this.keyword,
        "[resultsCount]": this.resultsCount,
      }

      const placeholders = Object.keys(replacements)
        .map(placeholder => escapeRegExp(placeholder))
        .join("|")

      const regExp = new RegExp(placeholders, "gi")

      return string.replace(regExp, match => replacements[match])
    },

    /**
     * START: Fix for Safari on iOS (https://github.com/shentao/vue-multiselect/issues/709)
     */
    addMultiSelectOverlay(el) {
      el.curPointer = el.pointer
      el.$el.setAttribute("aria-expanded", "true")
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
      })
    },

    removeMultiSelectOverlay() {
      window.removeEventListener("keydown", this.keydownHandler)
      this.$el.querySelectorAll(".multiselect__content-wrapper").forEach(item => {
        item.setAttribute("tabindex", -1)
      })
      const overlay = document.querySelector(".multiselect__overlay")

      if (overlay) {
        overlay.remove()
      }
    },

    onCloseFilterSelect(el) {
      el.deactivate()
      el.$el.setAttribute("aria-expanded", "false")
      this.removeMultiSelectOverlay()
    },
    /**
     * END: Fix for Safari on iOS
     */
  },
}
</script>

<style lang="scss">
.multiselect {
  margin-bottom: 10px;
}

.multiselect__content-wrapper {
  position: absolute;
  z-index: 10000;
  top: calc(100% - 1px);
  left: 0;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  width: 100%;
}

.multiselect__icon,
.multiselect__caret-icon {
  margin-right: 5px;
}
.multiselect__caret-icon {
  margin-right: 0;
  position: absolute;
  right: 15px;
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
