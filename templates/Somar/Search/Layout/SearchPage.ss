<div class="container">
    <div class="row">
        <div class="col-12">
            <%-- Breadcrumbs --%>
            <div class="breadcrumbs">
                <a href="/" title="Home" class="breadcrumbs__item breadcrumbs__home">
                    <i class="material-icons">home</i>
                </a>
                <div class="breadcrumbs__separator">&rsaquo;</div>
                <div class="breadcrumbs__item">
                    Search
                </div>
            </div>
        </div>
        <%--Search container--%>
        <section class="col-12 col-md-12 col-lg-8 offset-lg-2">
            <h1 class="text-center mb-2">$Title</h1>
            <%-- Search form--%>
            <form role="search" class="search-form--desktop" method="get" action="search">
                <label class="sr-only" for="search">Search</label>
                <input class="form__input" name="search" type="text" required="true" value="$Query"/>
                <button class="search-button" type="submit" aria-label="search" title="search">
                    <i class="search-icon material-icons">search</i>
                </button>
            </form>
            <%--Search results--%>
            <% if $Results %>
                <% loop $Results %>
                    <a href="$Link" class="search-card">
                        <span class="search-card__header">
                            <h2 class="search-card__title mb-0">$Title</h2>
                            <i class="search-card__chevron material-icons">chevron_right</i>
                        </span>
                        <span class="search-card__body">$Summary</span>
                    </a>
                <% end_loop %>
            <% else %>
                <div>
                    <h3>Sorry, we couldn't find any results matching "$Query"</h3>
                    <p class="mt-3 mb-2">Search tips:</p>
                    <ul>
                        <li>Check your spelling and try again</li>
                        <li>Try different or less specific key words</li>
                    </ul>
                    <p>Alternatively, you may find what you are looking for on one of the following pages.</p>
                    <p>
                        <a href="/" class="btn btn--primary">Home</a>
                        <a href="/services" class="btn btn--primary">Services</a>
                        <a href="/getting-started" class="btn btn--primary">Getting Started</a>
                        <a href="/customer-services" class="btn btn--primary">Customer services</a>
                        <a href="/more-information" class="btn btn--primary">More information</a>
                    </p>
                </div>
            <% end_if %>
            <!-- Pagination -->
            <% if $Results.MoreThanOnePage %>
                <div class="pagination">
                    <% if $Results.NotFirstPage %>
                        <a class="pagination__prev" href="$Results.PrevLink">
                            <i class="material-icons">chevron_left</i>
                        </a>
                    <% end_if %>
                    <ul class="pagination__list">
                        <% loop $Results.Pages %>
                            <li <% if $CurrentBool %> class="pagination__item active" <% else %>
                                                      class="pagination__item"<% end_if %> >
                                <a class="pagination__link" href="$Link">$PageNum</a>
                            </li>
                        <% end_loop %>
                    </ul>
                    <% if $Results.NotLastPage %>
                        <a class="pagination__next" href="$Results.NextLink">
                            <i class="material-icons">chevron_right</i>
                        </a>
                    <% end_if %>
                </div>
            <% end_if %>
        </section>
    </div>
</div>
