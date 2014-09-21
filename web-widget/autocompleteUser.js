(function ($) {
  var attrs = "uid,mail,displayName,cn,employeeType,departmentNumber,eduPersonPrimaryAffiliation,supannEntiteAffectation,supannRoleGenerique,supannEtablissement";
    var affiliation2order = { staff: 1, teacher: 2, researcher: 3, emeritus: 4, student: 5, affiliate: 6, alum: 7, member: 8 };
  var affiliation2text = { teacher: "Enseignants", student: "Etudiants", staff: "Biatss", researcher: "Chercheurs", emeritus: "Professeurs &eacute;m&eacute;rites", affiliate: "Invit&eacute;", alum: "Anciens &eacute;tudiants", member: "Divers", "": "Divers" };

  var category2order = { structures: 5, affiliation: 5, diploma: 1, elp: 2, gpelp: 3, gpetp: 4 };

  var category2text = {
      structures: 'Groupes &Eacute;tablissement',
      affiliation: 'Groupes &Eacute;tablissement',
      diploma: 'Groupes &Eacute;tapes',
      elp: 'Groupes Mati&egrave;res',
      gpelp: 'Groupes TD'
  };
  var subAndSuper_category2text = {
      structures: 'Groupes parents',
      affiliation: 'Groupes parents',
      diploma: '&Eacute;tapes associ&eacute;es',
      elp: 'Mati&egrave;res associ&eacute;es',
      gpelp: 'Groupes TD associ&eacute;s'
  };

  var symbol_select = "\u229E";
  var symbol_navigate = "\u21B8";

  var highlight = function (text) {
      return "<span class='match'>" + text + "</span>";
  };

  var getDetails = function (item) {
      var details = [];

      if (item.searchedTokenL === item.mail.toLowerCase()) {
	  details.push(highlight(item.mail));
      } else if (item.duplicateDisplayName) {
	  details.push(item.mail);
      }
      if (item.employeeType)
	  details.push(item.employeeType.join(" - "));
      if (item.supannRoleGenerique)
	  details.push(item.supannRoleGenerique.join(" - "));
      if (item.supannEntiteAffectation) {
	  var prev = details.pop();
	  details.push((prev ? prev + " - " : '') + item.supannEntiteAffectation.join(" - "));
      }
      if (item.departmentNumber) {
	  details.push((item.departmentNumber.count >= 2 ? "Disciplines : " : "Discipline : ") + item.departmentNumber.join(' - '));
      }
      if (item.supannEtablissement)
	  details.push(item.supannEtablissement.join(" - "));

      if (details.length) 
	  return "<div class='details'>" + details.join("<br>") + "</div>"
      else
	  return "";
  };

  var highlightMatched = function (text, searchedTokenL) {
	var textL = text.toLowerCase();
	var pos = textL.search(searchedTokenL);
	if (pos < 0) 
	    return textL;
	else {
	    var endPos = pos + searchedTokenL.length;
	    return text.substring(0, pos) + 
		highlight(text.substring(pos, endPos)) +
		text.substring(endPos);
	}
  };

  var getNiceDisplayName = function (item) {
      var uid = item.uid;
      var displayName = item.displayName;
      var searchedTokenL = item.searchedTokenL;
      var display_uid = item.duplicateDisplayName;
      if (uid === searchedTokenL) {
	  display_uid = true;
	  uid = highlight(uid);
      } else if (item.cn.toLowerCase().indexOf(searchedTokenL) === 0)
	  displayName = highlightMatched(item.cn, searchedTokenL);
      else
	  displayName = highlightMatched(displayName, searchedTokenL);

      if (display_uid)
	  displayName += " (" + uid + ")";

      return displayName;
  };

  var renderOneWarning = function(ul, msg) {
      return $("<li></li>").addClass("warning").append(msg).appendTo(ul);
  };

  var renderWarningItem = function(ul, item) {
      var li = $();
      if (item.nbListeRouge)
	  li = renderOneWarning(ul, 
	      item.nbListeRouge > 1 ?
		  "NB : des r&eacute;sultats ont &eacute;t&eacute; cach&eacute;s<br>&agrave; la demande des personnes." :
		  "NB : un r&eacute;sultat a &eacute;t&eacute; cach&eacute;<br>&agrave; la demande de la personne."
	  );

      if (item.partialResults)
	  li = renderOneWarning(ul, "Votre recherche est limit&eacute;e &agrave; " + item.partialResults + " r&eacute;sultats.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.");
      if (item.partialResultsNoFullSearch)
	  li = renderOneWarning(ul, "Votre recherche est limit&eacute;e.<br>Pour les autres r&eacute;sultats, veuillez affiner la recherche.");

      if (item.wsError)
	  li = renderOneWarning(ul, "Erreur web service");

      return li;
  };
  var myRenderItemRaw = function(ul, item, moreClass, renderItemContent) {
	if (item.warning) 
	    return renderWarningItem(ul, item);

	if (item.pre)
	    $("<li class='kind ui-menu-divider'><span>" + item.pre + "</span></li>").appendTo(ul);

	var content = renderItemContent(item);
      return $("<li></li>").addClass(item.odd_even ? "odd" : "even").addClass(moreClass)
	    .data("item.autocomplete", item)
	    .append("<a>" + content + "</a>")
	    .appendTo(ul);

  };
  var myRenderUserItem = function (ul, item) {
      return myRenderItemRaw(ul, item, 'userItem', function (item) {
	  return getNiceDisplayName(item) + getDetails(item);
      });
  };

  var countOccurences = function (list) {
	var r = {};
	$.each(list, function (i, e) {
	    r[e] = (r[e] || 0) + 1;
	});
	return r;
  };

  var sortByAffiliation = function (items) {
      return items.sort(function(a,b) { 
	  return (affiliation2order[a.eduPersonPrimaryAffiliation] || 99) - (affiliation2order[b.eduPersonPrimaryAffiliation] || 99);
      });
  }

  var transformItems = function (items, wantedAttr, searchedToken) {
      var searchedTokenL = searchedToken.toLowerCase();
      var affiliation;
      var odd_even;
      // nb: "cn" is easer to compare since there is no accents. Two "displayName"s could be equal after removing accents.
      var cnOccurences = countOccurences($.map(items, function (item) { return item.cn }));
      var displayNameOccurences = countOccurences($.map(items, function (item) { return item.displayName }));
      $.each(items, function ( i, item ) {
	    item.label = item.displayName;
	    item.value = item[wantedAttr] || 'unknown';
	    item.searchedTokenL = searchedTokenL;

	    if (affiliation != item.eduPersonPrimaryAffiliation) {
		affiliation = item.eduPersonPrimaryAffiliation;
		item.pre = affiliation2text[affiliation || ""];
	    }

	    if (displayNameOccurences[item.displayName] > 1 || cnOccurences[item.cn] > 1)
		item.duplicateDisplayName = true;

	    item.odd_even = odd_even = !odd_even;
	});
  };

  function disableEnterKey(input) {
      input.keydown(function(event){
	      var keyCode = $.ui.keyCode;
      	      switch( event.keyCode ) {
      	      case keyCode.ENTER:
	      case keyCode.NUMPAD_ENTER:
		  event.preventDefault();
		  event.stopPropagation();    
	      }
      });
  }

  function ui_autocomplete_data(input) {
      return input.data("ui-autocomplete") || input.data("autocomplete"); // compatibility with jquery-ui <= 1.8.x
  }

  var myOpen = function () {
      var menu = ui_autocomplete_data($(this)).menu.element;
      var menu_bottom = menu.position().top + menu.outerHeight();
      var window_bottom = $(window).scrollTop() + $(window).height();
      if (window_bottom < menu_bottom) {
	  var best_offset = $(window).scrollTop() + menu_bottom - window_bottom;
	  var needed_offset = $(this).offset().top
	  $('html,body').scrollTop(Math.min(needed_offset, best_offset));
      }
  };
    
  $.fn.autocompleteUser = function (searchUserURL, options) {
      if (!searchUserURL) throw "missing param searchUserURL";

      var settings = $.extend( 
	  { 'minLength' : 2,
	    'minLengthFullSearch' : 4,
	    'maxRows' : 10,
	    'wantedAttr' : 'uid',
	    'disableEnterKey': false,
	    'attrs' : attrs
	  }, options);

      var wsParams = $.extend({ 
	  maxRows: settings.maxRows, 
	  attrs: settings.attrs + "," + settings.wantedAttr
      }, settings.wsParams);

      var input = this;

      var source = function( request, response ) {
	  wsParams.token = request.term;
	    $.ajax({
		url: searchUserURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchUserURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (dataAll) {
		    data = $.grep(dataAll, function (item, i) { 
			return item.displayName !== "supannListeRouge"; 
		    });
		    nbListeRouge = dataAll.length - data.length;

		    data = sortByAffiliation(data);
		    transformItems(data, settings.wantedAttr, request.term);

		    warning = { warning: true }
		    data.unshift(warning);
		    if (data.length >= settings.maxRows) {
			warning.partialResults = settings.maxRows;;
		    } else if (request.term.length < settings.minLengthFullSearch) {
			warning.partialResultsNoFullSearch = 1;
		    }
		    warning.nbListeRouge = nbListeRouge;

		    response(data);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: myOpen
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }

      if (settings.disableEnterKey) disableEnterKey(input);

      input.autocomplete(params);

      ui_autocomplete_data(input)._renderItem = myRenderUserItem;

      // below is useful when going back on the search values
      input.click(function () {
      	  input.autocomplete("search");
      });
  };


  var transformGroupItems = function (items, wantedAttr, searchedToken) {
      var searchedTokenL = searchedToken.toLowerCase();
      var category;
      var odd_even;
      $.each(items, function ( i, item ) {
	    item.label = item.name;
	    item.value = item[wantedAttr];
	    item.searchedTokenL = searchedTokenL;

	    if (category != item.category) {
		category = item.category;
		item.pre = category2text[category || ""] || 'Autres types de groupes';
	    }
	    item.odd_even = odd_even = !odd_even;
	});
  };

  function object_values(o) {
      return $.map(o, function (e) { return e; })
  }

  // ["aa", "aaa", "ab"] => "a"
  function find_common_prefix(list){
      var A = list.slice(0).sort(), word1 = A[0], word2 = A[A.length-1];
      var len = word1.length, i= 0;
      while(i < len && word1.charAt(i)=== word2.charAt(i)) i++;
      return word1.substring(0, i);
  }

  // ["aa", "aaa", "ab"] => ["a", "aa", "b"]
  function remove_common_prefix(list) {
      var offset = find_common_prefix(list).length;
      return $.map(list, function(e) {
	  return e.substring(offset);
      });
  }

  var simplifySubGroups = function (subGroups) {
      var names = $.map(subGroups, function (e) { return e.name });
      var offset = find_common_prefix(names).length;
      $.each(subGroups, function(i, e) {
	  e.name = e.name.substring(offset);
      });
  };
 
  var flattenSuperGroups = function (superGroups, groupId) {
      // remove current group
      delete superGroups[groupId];
      return sortByGroupCategory(object_values(superGroups));
  };

  var transformSubAndSuperGroups = function (items, wantedAttr) {
      var categoryText;
      var odd_even;
      $.each(items, function ( i, item ) {
	    item.label = item.name;
	    item.value = item[wantedAttr];

	    var categoryText_ = item.selected ? 'Selectionn&eacute;' : subAndSuper_category2text[item.category || ""] || 'Autres types de groupes';
	    if (categoryText != categoryText_) {
		item.pre = categoryText = categoryText_;
	    }
	    item.odd_even = odd_even = !odd_even;
	});
  };

    var onNavigate = function (input, settings) {
	var response = function (items) {
	    ui_autocomplete_data(input)._suggest(items);
	};
	return function (item) {
	    var allItems = [];
	    var cookAndAddReponses = function (items) {
		allItems = $.merge(allItems, items);
		transformSubAndSuperGroups(items, settings.wantedAttr);
		response(allItems);
	    };

	    var current = $.extend({}, item);
	    current.selected = true;
	    cookAndAddReponses([current]);

	    var wsParams = $.extend({ 
		key: item.key,
		depth: 99
	    }, settings.wsParams);

	    $.ajax({
		url: settings.subAndSuperGroupsURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchGroupURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (data) {
		    var subGroups = sortByGroupCategory(data.subGroups);
		    simplifySubGroups(subGroups);
		    var superGroups = flattenSuperGroups(data.superGroups, item.key);
		    var items = $.merge(subGroups, superGroups);
		    cookAndAddReponses(items);
		}
	    });
      };
    };

  var myRenderGroupItem = function (navigate) {
     return function (ul, item) {
	if (item.warning) 
	     return renderWarningItem(ul, item);

	if (item.pre)
	    $("<li class='kind'><span>" + item.pre + "</span></li>").appendTo(ul);

	var content = item.name;
        var li = $("<li></li>").addClass(item.odd_even ? "odd" : "even").addClass('groupItem')
	     .data("item.autocomplete", item);

	var button_navigate;
	if (navigate && !item.selected) {
	  button_navigate = $("<a style='display: inline' href='#'>" + symbol_navigate + "</a>").click(function (event) {
	    var item = $(this).closest("li").data("item.autocomplete");
	    navigate(item);
	    return false;
	  });
	  li.append($("<big>").append(button_navigate));
	}
        li.append($("<a style='display: inline' >")
		   .append(content + " &nbsp;")
		   .append($("<big>").append(symbol_select)));
	li.appendTo(ul);
     };
  };

  function sortByGroupCategory (items) {
      return items.sort(function (a, b) {
	  var cmp = (category2order[a.category] || 99) - (category2order[b.category] || 99);
          return cmp ? cmp : a.name.localeCompare(b.name);
      });
  }

  $.fn.autocompleteGroup = function (searchGroupURL, options) {
      if (!searchGroupURL) throw "missing param searchGroupURL";

      var settings = $.extend( 
	  { 'minLength' : 3,
	    'maxRows' : 20,
	    'wantedAttr' : 'key',
	    'disableEnterKey': false
	  }, options);

      var wsParams = $.extend({ 
	  maxRows: settings.maxRows
      }, settings.wsParams);

      var input = this;

      var source = function( request, response ) {
	  wsParams.token = request.term;
	    $.ajax({
		url: searchGroupURL,
		dataType: "jsonp",
		crossDomain: true, // needed if searchGroupURL is CAS-ified or on a different host than application using autocompleteUser
		data: wsParams,
		error: function () {
		    // we should display on error. but we do not have a nice error to display
		    // the least we can do is to show the user the request is finished!
		    response([ { warning: true, wsError: true } ]);
		},
		success: function (data) {
		    data = sortByGroupCategory(data);
		    transformGroupItems(data, settings.wantedAttr, request.term);

		    warning = { warning: true }
		    data.push(warning);
		    if (data.length >= settings.maxRows) {
			warning.partialResults = settings.maxRows;;
		    }
		    response(data);
		}
	    });
      };

      var params = {
	  minLength: settings.minLength,
	  source: source,
	  open: myOpen
      };

      if (settings.select) {
	  params.select = settings.select;
	  params.focus = function () {
	    // prevent update of <input>
	    return false;
	  };
      }

      if (settings.disableEnterKey) disableEnterKey(input);

      input.autocomplete(params);

      var navigate = settings.subAndSuperGroupsURL && onNavigate(input, settings);
      ui_autocomplete_data(input)._renderItem = myRenderGroupItem(navigate);

      // below is useful when going back on the search values
      input.click(function () {
      	  input.autocomplete("search");
      });
  };


  $.fn.handlePlaceholderOnIE = function () {

      var handlePlaceholder = 'placeholder' in document.createElement('input');
      if (handlePlaceholder) return; // cool, the browser handle it, nothing to do

      this.each(function(){
	  var o = $(this);
	  if (o.attr("placeholder") =="") return;

          var prevColor;
          var displayPlaceholder = function(){
	      if(o.val()!="") return;
              o.val(o.attr("placeholder"));
              prevColor = o.css("color");
              o.css("color", "#808080");
	  };
	  o.focus(function(){
              o.css("color", prevColor);
	      if(o.val()==o.attr("placeholder")) o.val("");
	  });
	  o.blur(displayPlaceholder);
          displayPlaceholder();
      });

  };

})(jQuery);
