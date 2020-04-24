<template>
  <div class="search__header">
    <h2 v-if="searchedKeyword && !loadingResults">{{ resultsCount }} results found for ‘{{ searchedKeyword }}’</h2>
    <h2 v-else-if="keyword">Searching for ‘{{ keyword }}’ ...</h2>
    <h2 v-else>{{ config.labels.title }}</h2>
    <div class="search__keyword">
      <input type="search" class="search__input" v-model="keyword" @input="onKeywordChange" />
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
        <div v-for="filterConfig in config.filters" class="col-md-6" :key="filterConfig.name">
          <multiselect
            class="multiselect--multiple"
            v-model="filters[filterConfig.name]"
            track-by="value"
            label="name"
            :placeholder="filterConfig.placeholder"
            :options="filterConfig.options"
            :multiple="true"
            :searchable="false"
            @input="onFilterChange"
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
            @input="onDateFilterChange"
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
