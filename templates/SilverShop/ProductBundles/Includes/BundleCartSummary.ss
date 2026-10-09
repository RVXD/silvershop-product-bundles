<%-- Compact list of a bundle's contents, for a cart/order line. Include it with the bundle in scope,
     e.g. in a cart item row: <% with $Product %><% include SilverShop\ProductBundles\BundleCartSummary %><% end_with %>
     Outputs nothing for non-bundle products. --%>
<% if $BundleItems %>
<ul class="bundle-cart-summary" style="list-style:none;margin:.25rem 0 0;padding:0;font-size:.85rem;color:#666">
    <% loop $BundleItems %>
        <li>{$Quantity}&times; $Product.Title</li>
    <% end_loop %>
</ul>
<% end_if %>
