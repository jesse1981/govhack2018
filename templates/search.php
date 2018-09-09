<html>
  <head>
    <meta charset="UTF-8">
    <title>Search Home</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/instantsearch.js@2.3/dist/instantsearch.min.css">
    <!-- Always use `2.x` versions in production rather than `2` to mitigate any side effects on your website,
    Find the latest version on InstantSearch.js website: https://community.algolia.com/instantsearch.js/v2/guides/usage.html -->
    <link rel="stylesheet" type="text/css" href="style.css">
  </head>
  <body>
    <header>
      <div>
         <input id="search-input" placeholder="Search for products">
         <!-- We use a specific placeholder in the input to guides users in their search. -->
      </div>
    </header>
    <?php echo $view; ?>
    <script src="https://cdn.jsdelivr.net/npm/instantsearch.js@2.3/dist/instantsearch.min.js"></script>
    <script src="app.js"></script>
  </body>
</html>
