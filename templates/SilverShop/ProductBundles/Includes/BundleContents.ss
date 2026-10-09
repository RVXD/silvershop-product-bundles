<%-- Renders a bundle's contents and savings. Safe to include on any product template: the guard below
     means it outputs nothing for non-bundle products. Expects a BundleProduct in scope. --%>
<% if $BundleItems %>
<section class="bundle-contents">
    <style>
        .bundle-contents{margin:1.5rem 0;border-top:1px solid #e2e2e2;padding-top:1.25rem}
        .bundle-contents h2{margin:0 0 .75rem;font-size:1.2rem}
        .bundle-contents__list{list-style:none;margin:0 0 1rem;padding:0}
        .bundle-contents__item{display:flex;align-items:center;gap:.6rem;padding:.45rem 0;border-bottom:1px solid #f0f0f0}
        .bundle-contents__item img{border-radius:4px;object-fit:cover;flex:0 0 auto}
        .bundle-contents__qty{font-weight:700;color:#555;min-width:2.2em}
        .bundle-contents__item a{flex:1;text-decoration:none;color:inherit}
        .bundle-contents__price{color:#777;font-size:.9rem;white-space:nowrap}
        .bundle-contents__savings{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin:.75rem 0 0}
        .bundle-contents__was{color:#999;text-decoration:line-through}
        .bundle-contents__now{font-size:1.3rem;font-weight:700}
        .bundle-contents__badge{background:#2e7d32;color:#fff;border-radius:4px;padding:2px 9px;font-size:.8rem;font-weight:600}
        .bundle-contents__extras-heading{margin:1.1rem 0 .4rem;font-size:1rem}
        .bundle-contents__item--optional .bundle-contents__price{color:#2e7d32;font-weight:600}
    </style>

    <h2><%t SilverShop\ProductBundles.ContentsHeading "What's in this bundle" %></h2>

    <ul class="bundle-contents__list">
        <% loop $RequiredItems %>
            <li class="bundle-contents__item">
                <% if $Product.Image.Exists %><img src="$Product.Image.Fill(56,56).URL" alt="$Product.Title.ATT" width="56" height="56"><% end_if %>
                <span class="bundle-contents__qty">{$Quantity}&times;</span>
                <a href="$Product.Link">$BuyableTitle</a>
                <span class="bundle-contents__price">$SubTotal.Nice</span>
            </li>
        <% end_loop %>
    </ul>

    <% if $OptionalItems %>
        <h3 class="bundle-contents__extras-heading"><%t SilverShop\ProductBundles.Extras "Optional extras" %></h3>
        <ul class="bundle-contents__list">
            <% loop $OptionalItems %>
                <li class="bundle-contents__item bundle-contents__item--optional">
                    <% if $Product.Image.Exists %><img src="$Product.Image.Fill(56,56).URL" alt="$Product.Title.ATT" width="56" height="56"><% end_if %>
                    <span class="bundle-contents__qty">{$Quantity}&times;</span>
                    <a href="$Product.Link">$BuyableTitle</a>
                    <span class="bundle-contents__price">+$SubTotal.Nice</span>
                </li>
            <% end_loop %>
        </ul>
    <% end_if %>

    <% loop $ChoiceGroups %>
        <% if $Items %>
            <h3 class="bundle-contents__extras-heading">$Title <small>(<%t SilverShop\ProductBundles.Choose "choose {range}" range=$ChoiceRange %>)</small></h3>
            <ul class="bundle-contents__list">
                <% loop $Items %>
                    <li class="bundle-contents__item bundle-contents__item--optional">
                        <% if $Product.Image.Exists %><img src="$Product.Image.Fill(56,56).URL" alt="$Product.Title.ATT" width="56" height="56"><% end_if %>
                        <span class="bundle-contents__qty">{$Quantity}&times;</span>
                        <a href="$Product.Link">$Product.Title</a>
                        <span class="bundle-contents__price">+$SubTotal.Nice</span>
                    </li>
                <% end_loop %>
            </ul>
        <% end_if %>
    <% end_loop %>

    <% if $HasSavings %>
        <p class="bundle-contents__savings">
            <span class="bundle-contents__was">$ComponentsPrice.Nice</span>
            <strong class="bundle-contents__now">$Price.Nice</strong>
            <span class="bundle-contents__badge"><%t SilverShop\ProductBundles.Save "Save {amount} ({percent}%)" amount=$Savings.Nice percent=$SavingsPercentage %></span>
        </p>
    <% end_if %>
</section>
<% end_if %>
