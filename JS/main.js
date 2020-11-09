window.addEventListener("DOMContentLoaded", function() {
  let input = document.getElementById("urlInput");
  let newPageBtn = document.getElementById("newSite");

  newPageBtn.addEventListener("click", (e) => {
    console.log(input.value);
    if (input.value !== "") {
      crawlSite(input.value)
      .then(res => {
        console.log(res);
        console.log(res.data);
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }
  });
});

/*crawlSite("https://www.superiorcleaning.solutions/")
.then(res => {
  console.log(res);
  console.log(res.data);
});*/

// Input: Page URL.
// Output: None.
// Crawls a given sitemap and extracts metadata (title, h1, h2, h3). 
// This metadata gets stored into a database.
/*const crawlSite = (sitemapUrl) => {
  return new Promise((resolve, reject) => {
    // The url used for the AJAX request
    let reqUrl = `${scriptUrl}?url=${pageUrl}`;

    // Send countUp.php this page's URL.
    let request = new XMLHttpRequest();
    request.open("GET", reqUrl, true);
    request.setRequestHeader("Access-Control-Allow-Origin", "*");
    request.setRequestHeader("Access-Control-Allow-Credentials", "true");
    request.setRequestHeader("Access-Control-Allow-Methods", "GET,HEAD,OPTIONS,POST,PUT");
    request.setRequestHeader("Access-Control-Allow-Headers", "Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Access-Control-Allow-Headers");
    
    request.onreadystatechange = () => {
      if (this.readyState == 4)
      {
        if (this.status == 200)
        {
        let result = this.responseText;
        resolve(result);
        }
      }
    }
    request.send();
  });
}*/

// Input: phpUrl is the url that links to the php script that will crawl the sitemap
//        data holds info to be used by the php script. Such info includes
//        data = { sitemap: "https://www.superiorcleaning.solutions/sitemap.xml" }
// Output: Response in json format
// Send the sitemap url to the php script that will fill the database accordingly
async function crawlSite(urlToCrawl) {
  let phpUrl = "http://localhost/dudaSearch/PHP/newSite.php";
  let sitemapUrl = urlToCrawl + "/sitemap.xml";
  // Default options are marked with *
  const response = await fetch(phpUrl, {
    method: 'POST', // *GET, POST, PUT, DELETE, etc.
    mode: 'cors', // no-cors, *cors, same-origin
    cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
    credentials: 'same-origin', // include, *same-origin, omit
    headers: {
      'Content-Type': 'application/json'
      // 'Content-Type': 'application/x-www-form-urlencoded',
    },
    redirect: 'follow', // manual, *follow, error
    referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
    body: JSON.stringify({
      sitemap: sitemapUrl
    }) // body data type must match "Content-Type" header
  });
  //console.log(response.text());
  return response.text();
  //return response.json();
}