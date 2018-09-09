<div class="content">
  <div class="search-form">
    <input name="search" id="search" />
  </div>
</div>
<div class="content">
  <div id="hits" style="display:none;"></div>
  <div id="single-result"></div>
</div>

<script type="text/html" id="hit-template">
  <div class="hit" rel="{{identifier}}">
    <div class="hit-image">
      <img src="{{publisher.imageUrl}}" alt="{{publisher.name}}">
    </div>
    <div class="hit-content">
      <span>{{title}}</span>
      <p class="hit-description">{{{_highlightResult.description.value}}}</p>
    </div>
  </div>
</script>
<script src="https://cdn.jsdelivr.net/npm/instantsearch.js@2.3/dist/instantsearch.min.js"></script>
<script src="/js/algolia.js"></script>
