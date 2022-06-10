function pricelist(year, sheetid, campsitename, campsitetype) {
  var ddlresultvar = "ddlresult" + year;
  var ddlfootnotevar = "ddlfootnotes" + year;
  var ddlresult = document.getElementById(ddlresultvar);
  var ddlfootnotes = document.getElementById(ddlfootnotevar);
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
  getJSON('https://sheets.googleapis.com/v4/spreadsheets/' + sheetid + '/values/Ark1!A2:i?key=AIzaSyCK61xNF64w35VbVHl6NiUzEju3EfHG-Q8', function (err, data) {
    if (err !== null) {
      alert('Something went wrong: ' + err);
    }
    else {
      items = data.values;
      var text = campsitename;
      var searchresult = '<b>' + campsitename + ' ' + year + '</b>' +
        '<div class="row mt-3 mt-md-3 mb-md-3">' +
        '<div class="col-12 col-md-12 mb-3 mb-md-0"><a href="javascript:void(0);" onclick="toggle_visibility();">Skift mellem lavsæson og højsæson</a></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-12 col-md-12 mb-3">';
      if (showhigh == 1) {
        searchresult += '<div class="highseason highcol" style="display: inline;"><b>Højsæson priser</b><br>' + seasonhigh + '</div>' +
        '<div class="lowseason lowcol" style="display: none;"><b>Lavsæson priser</b><br>' + seasonlow + '</div>';
      }
      else {
        searchresult += '<div class="highseason highcol" style="display: none;"><b>Højsæson priser</b><br>' + seasonhigh + '</div>' +
        '<div class="lowseason lowcol" style="display: inline;"><b>Lavsæson priser</b><br>' + seasonlow + '</div>';
      }

      searchresult += '</div></div>';

      if (showhigh == 1) {
        searchresult += '<div class="row col-header">' +
        '<div class="col-6 col-md-8"></div>' +
        '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" ><b>Normal</b></div>' +
        '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" ><b>Medlem</b></div>' +
        '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" ><b>Normal</b></div>' +
        '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" ><b>Medlem</b></div></div>';
      }
      else {
        searchresult += '<div class="row col-header">' +
        '<div class="col-6 col-md-8"></div>' +
        '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" ><b>Normal</b></div>' +
        '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" ><b>Medlem</b></div>' +
        '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" ><b>Normal</b></div>' +
        '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" ><b>Medlem</b></div></div>';
      }

      var heading = "";
      var footnotenr = 0;
      var footnotetext = "";
      var refnr = 0;
      for (var item, i = 0; item = items[i++];) {
        var kontrol = item[3].trim();
        if (campsitetype != '' && item[1] != campsitetype) {
          continue
        }
        if (kontrol != '') {
          var comment = item[8].trim();
          if (comment != '') {
            footnotenr = footnotenr + 1;
            footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[8] + '</div>';
            refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
          }
          else {
            refnr = '';
          }

          if (showhigh == 1) {
            searchresult += '<div class="pricerow bg-even row col-header py-2">' +
            '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
            '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + item[4] + '</div>' +
            '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + item[5] + '</div>' +
            '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + item[6] + '</div>' +
            '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + item[7] + '</div></div>';
          }
          else {
            searchresult += '<div class="pricerow bg-even row col-header py-2">' +
            '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
            '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + item[4] + '</div>' +
            '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + item[5] + '</div>' +
            '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + item[6] + '</div>' +
            '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + item[7] + '</div></div>';
          }
        }
      }

      for (var item, i = 0; item = items[i++];) {
        if (campsitetype != '' && item[1] != 'X') {
          continue;
        }
        var comment = item[8].trim();
        if (comment != '') {
          footnotenr = footnotenr + 1;
          footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[8] + '</div>';
          refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
        }
        else {
          refnr = '';
        }

        if (showhigh == 1) {
          searchresult += '<div class="row pricerow bg-even col-header py-2">' +
          '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
          '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + item[4] + '</div>' +
          '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + item[5] + '</div>' +
          '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + item[6] + '</div>' +
          '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + item[7] + '</div></div>';
        }
        else {
          searchresult += '<div class="row pricerow bg-even col-header py-2">' +
          '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
          '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + item[4] + '</div>' +
          '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + item[5] + '</div>' +
          '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + item[6] + '</div>' +
          '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + item[7] + '</div></div>';
        }
      }

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
            // if (item[2] == "Fastligger") {
            //   subheading1 = "<b>Normal</b>";
            //   subheading2 = "<b>Medlem</b>";
            // }


            if (showhigh == 1) {
              searchresult += '<div class="row pricerow mt-4 col-header py-2">' +
              '<div class="col-6 col-md-8 item"><strong>' + item[2] + '</strong></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" ></div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" ></div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + subheading1 + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + subheading2 + '</div>' +
              '</div>';
            }
            else {
              searchresult += '<div class="row pricerow mt-4 col-header py-2">' +
              '<div class="col-6 col-md-8 item"><strong>' + item[2] + '</strong></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" ></div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" ></div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + subheading1 + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + subheading2 + '</div>' +
              '</div>';
            }

            heading = item[2];
            show_bg = 1;

          }
          var comment = item[8].trim();
          if (comment != '') {
            footnotenr = footnotenr + 1;
            footnotetext += '<div class="footnote mb-3" ><a id=' + footnotenr + '><b>' + footnotenr + ')</b></a> ' + item[8] + '</div>';
            refnr = '<a href="#' + footnotenr + '">' + footnotenr + ')</a>';
          }
          else {
            refnr = '';
          }
          if (show_bg == 1) {
            if (showhigh == 1) {
              searchresult += '<div class="row pricerow bg-odd col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + item[4] + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + item[5] + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + item[6] + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + item[7] + '</div>' +
              '</div>';
            }
            else {
              searchresult += '<div class="row pricerow bg-odd col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + item[4] + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + item[5] + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + item[6] + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + item[7] + '</div>' +
              '</div>';
            }

            show_bg = 0;
          }
          else {
            if (showhigh == 1) {
              searchresult += '<div class="row pricerow col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: inline" >' + item[4] + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: inline" >' + item[5] + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: none" >' + item[6] + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: none" >' + item[7] + '</div>' +
              '</div>';
            }
            else {
              searchresult += '<div class="row pricerow col-header py-2">' +
              '<div class="col-6 col-md-8 item">' + item[3] + ' <sup>' + refnr + '</sup></div>' +
              '<div class="col-3 col-md-2 highnormal highcol text-right" style="display: none" >' + item[4] + '</div>' +
              '<div class="col-3 col-md-2 highmember highcol text-right" style="display: none" >' + item[5] + '</div>' +
              '<div class="col-3 col-md-2 lownormal lowcol text-right" style="display: inline" >' + item[6] + '</div>' +
              '<div class="col-3 col-md-2 lowmember lowcol text-right" style="display: inline" >' + item[7] + '</div>' +
              '</div>';
            }

            show_bg = 1;
          }
        }
      }
      searchresult += '<div class=mb-5 mt-5"></div>';

      for (var item, i = 0; item = items[i++];) {
        if (campsitetype != '' && item[1] != 'Fodnote') {
          continue;
        }
        footnotetext += '<div class="footnote mb-3"><h4>' + item[2] + '</h4><p>' + item[3] + '</p></div>';
      }
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
