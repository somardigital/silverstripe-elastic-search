<template>
  <div v-if="pageResults.length" class="search-results">
    <ul class="search-results__list">
      <li v-for="result in pageResults" :key="result.id" class="search-results__item">
        <a v-if="result.thumbnailURL" :href="result.url" class="search-results__thumbnail d-none d-sm-block">
          <img :src="result.thumbnailURL" :alt="result.title" />
        </a>
        <div class="search-results__details">
          <a :href="result.url">
            <h2 :class="['search-results__title', 'type-' + result.type]">{{ result.title }}</h2>
          </a>
          <p class="search-results__summary">{{ result.summary }}</p>
          <div class="d-flex">
            <a v-if="result.thumbnailURL" :href="result.url" class="search-results__thumbnail d-sm-none">
              <img :src="result.thumbnailURL" :alt="result.title" />
            </a>
            <div class="search-results__meta">
              <a v-if="result.fileURL" :href="result.fileURL" class="search-results__url" target="_blank">
                Download now <span v-if="result.fileMetaData">{{ result.fileMetaData }}</span>
              </a>
              <a v-else :href="result.url" class="search-results__url">{{ result.url | addHost }}</a>
              <span class="search-results__date">
                <template v-if="result.dateString">{{ result.dateString }}</template>
                <template v-else>Updated {{ result.date | dateFormat }}</template>
              </span>
            </div>
          </div>
        </div>
      </li>
    </ul>
    <nav aria-label="Search results pages">
      <ul class="pagination">
        <li v-if="currentPage > 1" class="pagination__prev">
          <a @click.prevent="currentPage--" href="#" class="btn btn-round btn-outline-primary btn-arrow-back"
            >Previous
          </a>
        </li>
        <li v-for="page in pageCount" :key="page" class="pagination__page">
          <a
            @click.prevent="currentPage = page"
            href="#"
            class="btn btn-circle btn-outline-primary"
            :class="{ active: currentPage == page }"
            >{{ page }}
          </a>
        </li>
        <li v-if="currentPage < pageCount" class="pagination__next">
          <a @click.prevent="currentPage++" href="#" class="btn btn-round btn-outline-primary btn-arrow">Next </a>
        </li>
      </ul>
    </nav>
  </div>
  <h1 v-else-if="errorMessage" class="search-results__message search-results__message--error">{{ errorMessage }}</h1>
  <h1 v-else class="search-results__message">No results found</h1>
</template>

<script>
export default {
  props: {
    errorMessage: String,
    results: Array,
    resultsPerPage: {
      type: Number,
      default: 10,
    },
  },
  data() {
    return {
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
  }
  &__meta {
    display: flex;
    flex-direction: column;
  }
}
.pagination {
  list-style-type: none;
  margin-bottom: 0;
  margin-top: 20px;
  padding: 0;
  li {
    display: inline-block;
  }
}
</style>
