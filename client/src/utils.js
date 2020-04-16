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
  const queryParams = []

  Object.keys(params).forEach(key => {
    if (Array.isArray(params[key])) {
      if (params[key].length) {
        queryParams.push(`${key}[]=${params[key].map(encodeURIComponent).join(`&${key}[]=`)}`)
      }
    } else {
      if (params[key] && params[key] != "null") {
        queryParams.push(`${key}=${encodeURIComponent(params[key])}`)
      }
    }
  })

  return `?${queryParams.join("&")}`
}
