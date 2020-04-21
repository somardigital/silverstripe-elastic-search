<template>
  <div id="search-app">
    <SearchHeader :resultsCount="results.length" :loadingResults="isLoading" @search="onSearch" />
    <SearchResults v-if="!isLoading" :results="results" :errorMessage="error" />
    <div v-else class="search__loader"><span class="sr-only">Loading...</span></div>
  </div>
</template>

<script>
import { buildSearchQueryString } from "@/utils"
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
      error: "",
      results: [],
    }
  },
  created() {},
  methods: {
    onSearch(params) {
      this.isLoading = true
      this.error = ""

      const pagePath = window.location.pathname.replace(/\/$/, "")
      const url = `${pagePath}/search${buildSearchQueryString(params)}`

      fetch(url, {
        headers: {
          Accept: "application/json",
        },
      })
        .then(response => {
          if (response.status >= 400 && response.status < 600) {
            throw new Error("Bad response from server")
          }
          return response.json()
        })
        .then(response => {
          if (response.error) {
            throw new Error("Search query error")
          }
          this.results = response.results ? response.results : []
        })
        .catch(() => {
          this.error = "An unexpected error ocurred, please refresh the page and try again"
          this.results = []
        })
        .finally(() => {
          this.isLoading = false
        })
    },
  },
}
</script>
