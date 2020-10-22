<template>
  <div class="search-results">
    <div class="search-results-inner">
      <template v-if="pageResults.length">
        <ul class="search-results__list">
          <li v-for="result in pageResults" :key="result.id" class="search-results__item">
            <a v-if="result.thumbnailURL" :href="result.url" class="search-results__thumbnail d-none d-sm-block">
              <img :src="result.thumbnailURL" :alt="result.title" />
            </a>
            <div class="search-results__details">
              <a class="search-results__details--title" :href="result.url">
                <h2 :class="['search-results__title', 'type-' + result.type]">{{ result.title }}</h2>
              </a>
              <div class="search-results__content">
                <p class="search-results__summary" v-html="result.summary"></p>
                <div class="search-results__extra-content" v-if="result.extraContent" v-html="result.extraContent" />
              </div>
              <div class="d-flex">
                <a v-if="result.thumbnailURL" :href="result.url" class="search-results__thumbnail d-sm-none">
                  <img :src="result.thumbnailURL" :alt="result.title" />
                </a>
                <div class="search-results__meta">
                  <a v-if="result.fileURL" :href="result.fileURL" class="search-results__url" target="_blank">
                    Download now <span v-if="result.fileMetaData">{{ result.fileMetaData }}</span>
                  </a>
                  <a v-else :href="result.url" class="search-results__url">
                    <template v-if="config.labels.resultLinkText">
                      {{ config.labels.resultLinkText }}<i class="search-results__url-icon" aria-hidden="true"></i>
                    </template>
                    <template v-else>
                      {{ result.url | addHost }}
                    </template>
                  </a>
                  <span v-if="result.dateString !== false" class="search-results__date">
                    <template v-if="result.dateString">{{ result.dateString }}</template>
                    <template v-else>Updated {{ result.date | dateFormat }}</template>
                  </span>
                </div>
              </div>
            </div>
          </li>
        </ul>
        <pagination :currentPage="currentPage" :pageCount="pageCount" :page.sync="currentPage" />
      </template>
      <template v-else>
        <h1 v-if="errorMessage" class="search-results__message search-results__message--error">{{ errorMessage }}</h1>
        <h1 v-else class="search-results__message">No results found</h1>
      </template>
    </div>
  </div>
</template>

<script>
import Pagination from "./Pagination"
import { searchConfig } from "@/utils"

export default {
  props: {
    errorMessage: String,
    results: Array,
    resultsPerPage: {
      type: Number,
      default: 10,
    },
  },
  components: { Pagination },
  data() {
    return {
      config: searchConfig,
      currentPage: 1,
    }
  },
  computed: {
    pageResults: function() {
      const start = (this.currentPage - 1) * this.resultsPerPage
      return this.results.slice(start, start + this.resultsPerPage)
    },
    pageCount: function() {
      return Math.ceil(this.results.length / this.resultsPerPage)
    },
  },
  methods: {
    changePage(page) {
      if (this.currentPage != page) {
        this.currentPage = page
        window.scrollTo(0, 0)
      }
    },
  },
  filters: {
    addHost: url => {
      return window.location.host + url
    },
    dateFormat: dateStr => {
      const months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"]
      const date = new Date(dateStr)
      const day = (date.getDate() < 10 ? "0" : "") + date.getDate()

      return `${day} ${months[date.getMonth()]} ${date.getFullYear()}`
    },
  },
}
</script>

<style lang="scss" scoped>
.search-results {
  &__list {
    list-style-type: none;
    padding: 0;
    margin: 0;
  }
  &__item {
    display: flex;
    &:not(:last-child) {
      margin-bottom: 20px;
    }
  }
  &__thumbnail {
    margin-right: 20px;
    max-width: 105px;
    flex-shrink: 0;
    img {
      max-width: 100%;
    }

    &:before {
      content: none;
    }
  }

  &__details {
    &--title {
      &:before {
        content: none;
      }
    }
  }

  &__meta {
    display: flex;
    flex-direction: column;
  }
}
</style>
