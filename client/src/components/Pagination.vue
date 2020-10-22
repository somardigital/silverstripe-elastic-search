<template>
  <nav aria-label="Search results pages">
    <ul class="pagination">
      <li v-if="currentPage > 1" class="pagination__prev">
        <button
          @click.prevent="$emit('update:page', currentPage - 1)"
          href="#"
          class="btn pagination__button pagination__button__prev"
        >
          Previous
        </button>
      </li>
      <li
        v-for="page in pageCount"
        :key="page"
        class="pagination__page"
        :class="{
          'pagination__page--active': currentPage == page,
          'pagination__page--first': page == 1,
          'pagination__page--last': page == pageCount,
        }"
      >
        <button
          @click.prevent="$emit('update:page', page)"
          href="#"
          class="btn pagination__button"
          :class="{
            'pagination__button--active': currentPage == page,
          }"
        >
          {{ page }}
        </button>
      </li>
      <li v-if="currentPage < pageCount" class="pagination__next">
        <button
          @click.prevent="$emit('update:page', currentPage + 1)"
          href="#"
          class="btn pagination__button pagination__button__next"
        >
          Next
        </button>
      </li>
    </ul>
  </nav>
</template>

<script>
export default {
  name: "Pagination",
  props: {
    currentPage: Number,
    pageCount: Number,
  },
}
</script>

<style lang="scss" scoped>
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
