const sampleConfig = {
  labels: {
    filtersHint: "Refine your serach results below by selecting popular filters and/or ordering them by date.",
  },
  filters: {
    type: {
      placeholder: "Type of content",
      options: [
        {
          name: "News",
          value: "news",
        },
        {
          name: "Event",
          value: "event",
        },
        {
          name: "Content",
          value: "content",
        },
      ],
    },

    date: {
      placeholder: "Type of content",
      options: [
        {
          name: "Most recent first",
          value: "desc",
        },
        {
          name: "Oldest first",
          value: "asc",
        },
        {
          name: "Select dates",
          value: "range",
        },
      ],
    },
  },
}
const configEl = document.getElementById("search-config")
export const searchConfig = configEl ? JSON.parse(configEl.innerHTML) : sampleConfig

export const debounce = (fn, time) => {
  let timeout

  return function() {
    const functionCall = () => fn.apply(this, arguments)

    clearTimeout(timeout)
    timeout = setTimeout(functionCall, time)
  }
}

export const buildSearchQueryString = params => {
  const encodedParams = { ...params }
  Object.keys(encodedParams).forEach(param => {
    encodedParams[param] = Array.isArray(encodedParams[param])
      ? encodedParams[param].map(encodeURIComponent)
      : encodeURIComponent(encodedParams[param])
  })

  let query = `?q=${encodedParams.keyword}`

  if (encodedParams.type && encodedParams.type.length) {
    query += `&type[]=${encodedParams.type.join("&type[]=")}`
  }

  if (encodedParams.sort) {
    query += `&sort=${encodedParams.sort}`
  }

  if (encodedParams.dateFrom && encodedParams.dateFrom != "null") {
    query += `&dateFrom=${encodedParams.dateFrom}`
  }

  if (encodedParams.dateTo && encodedParams.dateTo != "null") {
    query += `&dateTo=${encodedParams.dateTo}`
  }

  return query
}
