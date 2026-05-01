
<div class="search-summary">
    <p>Searched <% if $Types %><% loop $Types %><% if not $IsFirst %><% if $IsLast %> and <% else %>, <% end_if %><% end_if %><em>$Label</em><% end_loop %><% else %>everything<% end_if %><% if $Query %> for <em>"$Query"</em><% end_if %> and got $Results.TotalItems result<% if not $Results %>s<% else_if $Results.TotalItems > 1 %>s<% end_if %></p>
</div>