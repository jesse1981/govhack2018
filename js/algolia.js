$('#search').keyup(function(){
  if ($(this).val()) $('#hits').css('display','block');
  else $('#hits').css('display','none');
});

//var client = algoliasearch('DSO58QNV9B', '102c1f52316e259d4921fce301c0ef84');
//var index = client.initIndex('sources');

var search = instantsearch({
  // Replace with your own values
  appId: 'DSO58QNV9B',
  apiKey: '102c1f52316e259d4921fce301c0ef84', // search only API key, no ADMIN key
  indexName: 'sources',
  urlSync: false,
  searchParameters: {
    hitsPerPage: 10
  }
});

search.addWidget(
  instantsearch.widgets.searchBox({
    container: '#search'
  })
);

search.addWidget(
  instantsearch.widgets.hits({
    container: '#hits',
    templates: {
      item: document.getElementById('hit-template').innerHTML,
      empty: "We didn't find any results for the search <em>\"{{query}}\"</em>"
    }
  })
);

search.start();

function resetResultClicks() {
  $('.ais-hits--item').unbind('click');
  $('.ais-hits--item').click(function() {
    $('.ais-hits--item').removeClass('active');
    $(this).addClass('active');
    $.ajax({
      url: 'http://search.data.gov.au/api/v0/search/datasets?query="'+$(this).find('.hit').attr('rel')+'"&start=0&limit=1',
      success: function(res) {
        //var data = JSON.parse(res);
        data = res.dataSets[0];
        var e = $('#single-result');
        e.children().remove();
        var desc = data.description;
        desc = desc.replace("\r\n","<br/><br/>");
        e.append('<h3>'+data.title+'</h3>');
        e.append('<div class="publisher"><a href="'+data.publisher.website+'">'+data.publisher.name+'</a><br/><a href="mailto:'+data.contactPoint.identifier+'">'+data.contactPoint.identifier+'</a></div>');
        e.append('<div class="description">'+desc+'</div>');
        e.append('<div class="catalog" title="'+data.catalog+'" rel="'+data.catalog+'"></div>');

        e.append('<div class="dists"></div>');
        var dists = $('.dists')
        for (var i in data.distributions) {
          var updated = data.distributions[i].modified;
          dists.append('<div class="dist-item"><div class="'+data.distributions[i].format+'"></div><div class="dist-title"><a href="'+data.distributions[i].downloadURL+'">'+data.distributions[i].title+'</a><div class="modified">Last Updated: <span class="timeago" datetime="'+updated+'">'+updated+'</span></div></div>')
        }

        $(".timeago").timeago();
      }
    });
  });
}
document.addEventListener("DOMNodeInserted", function (event) {
  resetResultClicks();
}, false);
