/*!
 * agile-theme v1.0.0
 * A custom Drupal theme for the Dartmouth Digitial History Initiative.
 * (c) 2023 Agile Humanities Agency
 * MIT License
 * https://agile.git.beanstalkapp.com/agile-theme-builder-v002.git
 */

/**
 *  @file taxonomy_browser.js*
 */

(function ($, Drupal, once) {

  $.fn.extend({

    makeList: function(param={}) {
        if (this.length > 0) {
          var list = $("<ul></ul>").addClass('tb-list');

          if (typeof param.class === 'string') {
            list.addClass(param.class);
          }

          this.each(function (i,e) {
            $("<li></li>").html(e).appendTo(list);
          });
          return list;
        }
        return '';
    },

    taxonomyBrowser: function() {
      var _this = this;
      var endpoint = _this.data('endpoint');
      var initialRoute = _this.data('initial-route');
      var formatQuery =  "?_format=json"; // _format=json is Drupal’s required query when more than one interchange format is available
      var topic = _this.data('topic');

      initialize(_this);

      /** functions **/

      // Performs initial integrity checks and builds first panel.

      function initialize (element) {
        if (typeof endpoint != "undefined" && typeof initialRoute != "undefined") {
          build(initialRoute);
        }
      }

      // Complete build of panel from given route.

      function build(route) {
        var data = retrieveData(route + formatQuery);
        var termList =  $(processTerms(data)).makeList({class: 'tb-term-list'});
        var contentList = $(processContent(data)).makeList({class: 'tb-content-list'});


        if (data !== false) {
          makePanel([...termList,...contentList],data['parent']['label']);
        }
      }

      // Retrieves REST data from a given route.

      function retrieveData(route) {

        var _data = false;

        $.ajax({
          url: route,
          async: false,
          success: function(data) {
            _data = data;
          },
        });

        return _data;
      }

      // Extracts term from REST data and returns as an array of objects.

      function processTerms(data) {
        var elements = [];

        $(data.terms).each(function(i,term){
          elements.push(tbTermLink(term));
        });

        return elements;

      }

      // Extracts content from REST data and returns as an array of objects.

      function processContent(data) {
        var elements = [];

        $(data.content).each(function(i,cobj) {
          elements.push(tbContentLink(cobj));
        });

        return elements;
      }

      // Creates an anchor element from term data, including custom attributes.

      function tbTermLink(term) {
        const name = term.hasOwnProperty('displayName') ? term.displayName : term.name;
        return $('<a></a>').addClass("tb-term").attr('data-route', term.childRoute).attr('data-tid', term.tid).html(name);
      }

      // Creates an anchor element from a content data object, including custom attributes

      function tbContentLink(cobj) {
        return $('<a></a>').addClass("tb-content").addClass("type-" + cobj.type).attr('href',cobj.href).attr('data-id',cobj.id).html(cobj.displayTitle);
      }

      // Removes panels beyond the current one in preparation for new data

      function destroyPanels(current) {
        _this.find('.tb-panel').each(function(){
          if ($(this).data('panel') > current) {
            $(this).remove();
          }
        })
      }

      // Removes panels including the current one.

      function destroyCurrentPanel(currentPanel) {

        var panels = _this.find('.tb-panel');
        var previous = panels.first();
        var current = parseInt(currentPanel);
        panels.each(function(){
          // Cycle through panels and process the current one

          if ($(this).data('panel') >= current) {
            var position = previous.position().left;
            var tbTrack = $(this).closest('.tb-track');
            var currentElement = $(this);

            // Slide the viewer to the left, then destroy the current panel
            tbTrack.animate({
              scrollLeft: position + tbTrack.scrollLeft(),
              duration: 200,
              done: () => { currentElement.remove() } // remove current element when animation is complete
            });
          } else {
            previous = $(this);
          }
        })
      }

      // Creates a panel and prepares click event listeners for term links.

      function makePanel(contents,label=null) {
        // Prepare panel. data-panel is a numeric identifier for the panel.

        var panel = $("<div></div>").addClass('tb-panel').attr('data-panel',_this.children().length).html(contents);
        var panelHeader = $("<header></header>");

        // Add a label. @todo – have this pre-rendered through a template

        if (label !== null) {
          panelHeader.prepend("<div class='tb-group-label'>" + label + "</div>");
        }

        // Add a back element.

        if (_this.children().length - 1 > -1) {
          var grandparentLabel = _this.children().eq(_this.children().length - 1).find('.tb-group-label').text();

          var back = $('<a></a>').addClass('tb-back').attr('title','Back to ' + grandparentLabel);

          back.on('click',function(){
            destroyCurrentPanel($(this).closest('.tb-panel').data('panel')); // clean up panels
          })

          panelHeader.prepend(back);
        }

        panel.prepend(panelHeader);

        // Delegate event listeners to children

        panel.on('click','.tb-term',function(){
          destroyPanels($(this).closest('.tb-panel').data('panel')); // clean up panels
          build($(this).data('route'));
          $(this).closest('.tb-panel').find('.tb-term').removeClass('active'); // Only one active class per panel
          $(this).addClass('active');
        })

        panel.appendTo(_this);

        // Trigger event when panel has been added.

        $(document).trigger('tb-panel-change', {
          tbrowser: _this,
          panel: panel,
          track: _this.parent('.tb-track')
        });

      }
    }
  });

  // Initialize UI

  $(document).on('tb-panel-change',function(e,obj){

    var tbrowser = $(obj.tbrowser);
    const tbTrack = $(obj.track);
    const tbTrackWidth = tbTrack.innerWidth();
    var position = tbrowser.children().last().position().left;

    if (position >= tbTrackWidth) {
      tbTrack.animate({
        scrollLeft: position + tbTrack.scrollLeft()
      },200);
    }

  });

  Drupal.behaviors.initTaxonomyBrowser = {
    attach: function (context, settings) {
      once('initialize', '.taxonomy-browser', context).forEach(function (element) {
        $(element).taxonomyBrowser();
      });
    }
  };
})(jQuery, Drupal, once);
