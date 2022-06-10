function pricelist(year, sheetid, campsitename, campsitetype) {

  var ddlresultvar = "ddlresult" + year;
  var ddlfootnotevar = "ddlfootnotes" + year;
  var ddlresult = document.getElementById(ddlresultvar);
  var ddlfootnotes = document.getElementById(ddlfootnotevar);
  /* Translation */
  var headerhighseason = "Hoch&shy;saison Preis";
  var headerlowseason = "Neben&shy;saison Preis";
  var headernormalprice = "Normaler";
  var headermemberprice = "DCU Deal";
  var pricepermonth = "Pro Monat";
  var pricehalfyear = "Pro Halbes Jahr";
  var priceperyear = "Pro Jahr";
  var pricenote = "Siehe Note";
  var pricefree = "Kosten&shy;los";
  var switchtext = "Wechseln Sie zwischen Nebensaison und Hochsaison";

  /* Finder div elementer */

  var items = "";

  //Build an array containing Customer record //
  var getJSON = function (url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.responseType = 'json';
    xhr.onload = function () {
      var status = xhr.status;
      if (status === 200) {
        callback(null, xhr.response);
      }
      else {
        callback(status, xhr.response);
      }
    };
    xhr.send();
  };

  getJSON('https://sheets.googleapis.com/v4/spreadsheets/' + sheetid + '/values/Ark1!A2:o?key=AIzaSyCK61xNF64w35VbVHl6NiUzEju3EfHG-Q8',
    function (err, data) {
      if (err !== null) {
        alert('Something went wrong: ' + err);
      }
      else {
        items = data.values;
        var text = campsitename;

        var searchresult = '<b>' + campsitename + ' ' + year + '</b>' +
          '<div class="row mt-3 mt-md-3 mb-md-3">' +
          '<div class="col-12 col-md-8 mb-3 mb-md-0"><a href="javascript:void(0);" onclick="toggle_visibility();">' + switchtext + '</a></div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-12 col-md-12 mb-md-3">';

        if (showhigh == 1) {
          searchresult += '<div class="highseason highcol" style="display: inline;"><b>' + headerhighseason + '</b><br>' + seasonhigh + '</div>' +
          '<div class="lowseason lowcol" style="display: none;"><b>' + headerlowseason + '</b><br>' + seasonlow + '</div>' +
          '</div>' +
          '</div>';
        }
        else {
          searchresult += '<div class="highseason highcol" style="display: none;"><b>' + headerhighseason + '</b><br>' + seasonhigh + '</div>' +
          '<div class="lowseason lowcol" style="display: inline;"><b>' + headerlowseason + '</b><br>' + seasonlow + '</div>' +
          '</div>' +
          '</div>';
        }


        if (showhigh == 1) {
          searchresult += '<div class="row col-header">' +
            '<div class="col-6 col-md-8"></div>' +
            '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" ><b>' + headernormalprice + '</b></div>' +
            '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" ><b>' + headermemberprice + '</b></div>' +
            '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" ><b>' + headernormalprice + '</b></div>' +
            '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" ><b>' + headermemberprice + '</b></div>' +
            '</div>';
        }
        else {
          searchresult += '<div class="row col-header">' +
            '<div class="col-6 col-md-8"></div>' +
            '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" ><b>' + headernormalprice + '</b></div>' +
            '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" ><b>' + headermemberprice + '</b></div>' +
            '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" ><b>' + headernormalprice + '</b></div>' +
            '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" ><b>' + headermemberprice + '</b></div>' +
            '</div>';
        }


        var heading = "";
        var footnotenr = 0;
        var footnotetext = "";
        var refnr = 0;

        /* Første gennemløb giver priser for prisgruppen  */

        for (var item, i = 0; item = items[i++];) {
          var kontrol = item[3].trim();
          if (campsitetype != '' && item[1] != campsitetype) {
            continue
          }
          if (kontrol != '') {
            /* Finder linjer med kommentarer og laver referencenummer*/

            var comment = item[8].trim();
            if (comment != '') {
              footnotenr = footnotenr + 1;
              footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[13] + '</div>';
              refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
            }
            else {
              refnr = '';
            }

            var price4 = item[4];
            var price5 = item[5];
            var price6 = item[6];
            var price7 = item[7];
            price4 = price4.replace("pr/md", pricepermonth);
            price4 = price4.replace("pr. år", priceperyear);
            price4 = price4.replace("Gra&shy;tis", pricefree);
            price4 = price4.replace("se note", pricenote);
            price5 = price5.replace("pr/md", pricepermonth);
            price5 = price5.replace("pr. år", priceperyear);
            price5 = price5.replace("Gra&shy;tis", pricefree);
            price5 = price5.replace("se note", pricenote);
            price6 = price6.replace("pr/md", pricepermonth);
            price6 = price6.replace("pr. år", priceperyear);
            price6 = price6.replace("Gra&shy;tis", pricefree);
            price6 = price6.replace("se note", pricenote);
            price7 = price7.replace("pr/md", pricepermonth);
            price7 = price7.replace("pr. år", priceperyear);
            price7 = price7.replace("Gra&shy;tis", pricefree);
            price7 = price7.replace("se note", pricenote);

            if (showhigh == 1) {
              searchresult += '<div class="pricerow bg-even row col-header py-2">' +
                '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + price4 + '</div>' +
                '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + price5 + '</div>' +
                '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + price6 + '</div>' +
                '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + price7 + '</div>' +
                '</div>';
            }
            else {
              searchresult += '<div class="pricerow bg-even row col-header py-2">' +
                '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + price4 + '</div>' +
                '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + price5 + '</div>' +
                '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + price6 + '</div>' +
                '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + price7 + '</div>' +
                '</div>';
            }


          }
        }

        /* Andet gennemløb giver Generelle priser */
        for (var item, i = 0; item = items[i++];) {
          if (campsitetype != '' && item[1] != 'X') {
            continue;
          }

          /* finder linjer med kommentarer og laver referencenummer*/
          var comment = item[8].trim();
          if (comment != '') {
            footnotenr = footnotenr + 1;
            footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[13] + '</div>';
            refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
          }
          else {
            refnr = '';
          }
          var price4 = item[4];
          var price5 = item[5];
          var price6 = item[6];
          var price7 = item[7];
          price4 = price4.replace("pr/md", pricepermonth);
          price4 = price4.replace("pr. år", priceperyear);
          price4 = price4.replace("Gra&shy;tis", pricefree);
          price4 = price4.replace("se note", pricenote);
          price5 = price5.replace("pr/md", pricepermonth);
          price5 = price5.replace("pr. år", priceperyear);
          price5 = price5.replace("Gra&shy;tis", pricefree);
          price5 = price5.replace("se note", pricenote);
          price6 = price6.replace("pr/md", pricepermonth);
          price6 = price6.replace("pr. år", priceperyear);
          price6 = price6.replace("Gra&shy;tis", pricefree);
          price6 = price6.replace("se note", pricenote);
          price7 = price7.replace("pr/md", pricepermonth);
          price7 = price7.replace("pr. år", priceperyear);
          price7 = price7.replace("Gra&shy;tis", pricefree);
          price7 = price7.replace("se note", pricenote);

          if (showhigh == 1) {
            searchresult += '<div class="row pricerow bg-even col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + price4 + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + price5 + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + price6 + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + price7 + '</div>' +
              '</div>';
          }
          else {
            searchresult += '<div class="row pricerow bg-even col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + price4 + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + price5 + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + price6 + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + price7 + '</div>' +
              '</div>';
          }
        }

        /* Tredje gennemløb giver specifikke linjer for pladsen  */
        var show_bg = 0;
        for (var item, i = 0; item = items[i++];) {
          if (item[0] != text) {
            continue;
          }

          var kontrol = item[7].trim();
          var subheading1 = "";
          var subheading2 = "";

          if (kontrol != '') {
            if (heading != item[2]) {
              // if (item[2]=="Fastligger") {
              //   subheading1="<b>" & headernormalprice & "</b>";
              //   subheading2="<b>" & headermemberprice & "</b>";
              // }

              if (showhigh == 1) {
                searchresult += '<div class="row pricerow mt-4 col-header py-2">' +
                  '<div class="col-6 col-md-8 item"><strong>' + item[9] + '</strong></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" ></div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" ></div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + subheading1 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + subheading2 + '</div>' +
                  '</div>';
              }
              else {
                searchresult += '<div class="row pricerow mt-4 col-header py-2">' +
                  '<div class="col-6 col-md-8 item"><strong>' + item[9] + '</strong></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" ></div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" ></div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + subheading1 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + subheading2 + '</div>' +
                  '</div>';
              }

              heading = item[2];
              show_bg = 1;
            }

            /* Finder linjer med kommentarer og laver referencenummer*/
            var comment = item[8].trim();
            if (comment != '') {
              footnotenr = footnotenr + 1;
              footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[13] + '</div>';
              refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
            }
            else {
              refnr = '';
            }
            var price4 = item[4];
            var price5 = item[5];
            var price6 = item[6];
            var price7 = item[7];
            price4 = price4.replace("pr/md", pricepermonth);
            price4 = price4.replace("pr. år", priceperyear);
            price4 = price4.replace("Gra&shy;tis", pricefree);
            price4 = price4.replace("se note", pricenote);
            price5 = price5.replace("pr/md", pricepermonth);
            price5 = price5.replace("pr. år", priceperyear);
            price5 = price5.replace("Gra&shy;tis", pricefree);
            price5 = price5.replace("se note", pricenote);
            price6 = price6.replace("pr/md", pricepermonth);
            price6 = price6.replace("pr. år", priceperyear);
            price6 = price6.replace("Gra&shy;tis", pricefree);
            price6 = price6.replace("se note", pricenote);
            price7 = price7.replace("pr/md", pricepermonth);
            price7 = price7.replace("pr. år", priceperyear);
            price7 = price7.replace("Gra&shy;tis", pricefree);
            price7 = price7.replace("se note", pricenote);

            if (show_bg == 1) {
              if (showhigh == 1) {
                searchresult += '<div class="row pricerow bg-odd col-header py-2">' +
                  '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + price4 + '</div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + price5 + '</div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + price6 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + price7 + '</div>' +
                  '</div>';
              }
              else {
                searchresult += '<div class="row pricerow bg-odd col-header py-2">' +
                  '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + price4 + '</div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + price5 + '</div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + price6 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + price7 + '</div>' +
                  '</div>';
              }

              show_bg = 0;
            }
            else {
              if (showhigh == 1) {
                searchresult += '<div class="row pricerow col-header py-2">' +
                  '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + price4 + '</div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + price5 + '</div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + price6 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + price7 + '</div>' +
                  '</div>';
              }
              else {
                searchresult += '<div class="row pricerow col-header py-2">' +
                  '<div class="col-6 col-md-8 item">' + item[11] + ' <sup>' + refnr + '</sup></div>' +
                  '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + price4 + '</div>' +
                  '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + price5 + '</div>' +
                  '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + price6 + '</div>' +
                  '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + price7 + '</div>' +
                  '</div>';
              }

              show_bg = 1;
            }
          }
        }

        searchresult += '<div class=mb-5 mt-5"></div>';

        /* Fjerde gennemløb giver standard fodnoter */
        for (var item, i = 0; item = items[i++];) {
          if (campsitetype != '' && item[1] != 'Fodnote') {
            continue;
          }
          footnotetext += '<div class="footnote mb-3"><h4>' + item[9] + '</h4><p>' + item[11] + '</p></div>';
        }

        /* Hvis der ikke er noget søgeresultat  */
        if (searchresult == "") {
          searchresult = "<p>Intet fundet...</p>";
        }
        ddlresult.innerHTML = searchresult;
        ddlfootnotes.innerHTML = footnotetext;
      }
    });
};

function toggle_visibility() {
  highelements = document.getElementsByClassName("highcol");
  lowelements = document.getElementsByClassName("lowcol");
  for (var i = 0; i < highelements.length; i++) {
    highelements[i].style.display = highelements[i].style.display == 'inline' ? 'none' : 'inline';
  }
  for (var i = 0; i < lowelements.length; i++) {
    lowelements[i].style.display = lowelements[i].style.display == 'inline' ? 'none' : 'inline';
  }
}
