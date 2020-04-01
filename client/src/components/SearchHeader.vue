<template>
  <div class="search__header">
    <h2 v-if="searchedKeyword">{{ resultsCount }} results found for ‘{{ searchedKeyword }}’</h2>
    <h2 v-else>Start typing to search the content</h2>
    <div class="search__keyword">
      <input type="search" class="search__input" v-model="keyword" @input="onKeywordChange" />
      <i class="material-icons text-primary">search</i>
    </div>

    <div class="search__filters">
      <h3 class="search__hint">{{ config.labels.filtersHint }}</h3>
      <div class="row">
        <div class="col-md-6">
          <multiselect
            class="multiselect--multiple"
            v-model="typeFilter"
            track-by="value"
            label="name"
            :placeholder="config.filters.type.placeholder"
            :options="config.filters.type.options"
            :multiple="true"
            :searchable="false"
            @input="onTypeFilterChange"
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
        <div class="col-md-6">
          <multiselect
            v-model="dateFilter"
            track-by="value"
            label="name"
            placeholder="By date"
            :options="config.filters.date.options"
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
import { searchConfig, debounce } from "@/utils"
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
      typeFilter: [],
      dateFilter: "",
      dateFrom: null,
      dateTo: null,
      config: searchConfig,
    }
  },
  computed: {},
  watch: {
    loadingResults: function(isLoading) {
      if (!isLoading) {
        this.searchedKeyword = this.keyword
      }
    },
  },
  created() {
    let uri = window.location.search.substring(1)
    let params = new URLSearchParams(uri)

    this.searchedKeyword = this.keyword = params.get("q")

    this.search()
  },
  methods: {
    onKeywordChange() {
      this.debouncedSearch()
    },
    onTypeFilterChange() {
      this.search()
    },
    onDateFilterChange() {
      this.dateFrom = null
      this.dateTo = null

      if (this.dateFilter && this.dateFilter.value != "range") {
        this.search()
      }
    },

    debouncedSearch: debounce(function() {
      this.search()
    }, 500),

    search() {
      if (!this.keyword) {
        return
      }

      this.$emit("search", {
        keyword: this.keyword,
        type: this.typeFilter.map(filter => filter.value),
        sort: this.dateFilter && ["asc", "desc"].includes(this.dateFilter.value) ? this.dateFilter.value : "",
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
      })
    },
  },
}
</script>

<style lang="scss" scoped>
.multiselect {
  margin-bottom: 10px;
}
.search {
  &__keyword {
    margin-bottom: 20px;
    font-size: 1rem;
    position: relative;

    .material-icons {
      font-size: 36px;
      position: absolute;
      right: 8px;
      top: calc(50% - 18px);
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
}
</style>
