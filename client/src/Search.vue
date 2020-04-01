<template>
  <div id="search-app">
    <SearchHeader :resultsCount="results.length" :loadingResults="isLoading" @search="onSearch" />
    <SearchResults v-if="!isLoading" :results="results" :errorMessage="error" />
    <div v-else class="loader">Loading...</div>
  </div>
</template>

<script>
import SearchHeader from "./components/SearchHeader"
import SearchResults from "./components/SearchResults"

export default {
  name: "Search",
  components: {
    SearchHeader,
    SearchResults,
  },
  data() {
    return {
      isLoading: false,
      error: "This is error message",
      results: [],
    }
  },
  created() {},
  methods: {
    onSearch(params) {
      this.isLoading = true
      this.error = ""

      const url = `${window.location.href}/search${this.buildQueryString(params)}`

      fetch(url, {
        headers: {
          Accept: "application/json",
        },
      })
        .then(response => {
          return response.json()
        })
        .then(response => {
          this.results = response.results
        })
        .catch(() => {
          //console.error(error)
          this.error = "An unexpected error ocurred, please refresh the page and try again"
        })
        .finally(() => {
          this.isLoading = false
        })
    },

    buildQueryString(params) {
      let query = `?q=${params.keyword}`

      if (params.type.length) {
        query += `&type[]=${params.type.join("&type[]=")}`
      }

      if (params.sort) {
        query += `&sort=${params.sort}`
      }

      if (params.dateFrom) {
        query += `&dateFrom=${params.dateFrom}`
      }

      if (params.dateTo) {
        query += `&dateTo=${params.dateTo}`
      }
      return query
    },
  },
}
</script>
