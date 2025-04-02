
<% if $MoreThanOnePage %>
    <div class="pagination center">
    
        <% if $NotFirstPage %>
            <a class="prev button blue readmore" href="$PrevLink" title="View the previous page">Prev</a>
        <% else %>	
            <span class="prev disabled button">Prev</span>
        <% end_if %>
        <span class="current">
            Page <input class="pagination-goto" value="$CurrentPage" data-skip-autofocus="true" data-page-length="$PageLength" data-total-pages="$TotalPages" /> of $TotalPages
        </span>
        
        <% if $NotLastPage %>
            <a class="next button blue readmore" href="$NextLink" title="View the next page">Next</a>
        <% else %>	
            <span class="next disabled button">Next</span>
        <% end_if %>
        
    </div>
    
<% end_if %>