window.addEventListener("DOMContentLoaded", function() {
  const url = window.location.href;
  const results = document.getElementById("results");

  let input = document.getElementById("urlInput");
  let newPageBtn = document.getElementById("newSite");

  newPageBtn.addEventListener("click", (e) => {
    console.log(input.value);
    if (input.value !== "") {
      crawl(input.value)
      .then(res => {
        console.log(res);
        //console.log(res.data);
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }
  });

  let searchBar = document.getElementById("searchBar");
  let searchButton = document.getElementById("searchButton");

  searchButton.addEventListener("click", (e) => {
    console.log(searchBar.value);

    if (searchBar.value !== "") {
      search(searchBar.value, "https://www.armorshieldroof.com/")
      .then(res => {
        console.log(res);
        //console.log(res.data);
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }
  });

  let result = createResult({url: "https://www.armorshieldroof.com/", title: "Armor Shield: CT Roof Replacement, Repair, & Insurance Restoration", snippet: "Connecticut's leader in full-service roofing, roof replacement, and roof repair. See if you qualify to have your home owners insurance policy cover your roof replacement after the storm."});
  results.appendChild(result);
});

// Input: Object holding result metadata.
// Output: Result element.
// Creates a result element.
const createResult = (data = {url: null, title: null, snippet: null}) => {
  let result = document.createElement("div");
  let link = document.createElement("a");
  let url = document.createElement("h6");
  let title = document.createElement("h4");
  let snippet = document.createElement("p");

  // Format url portion of the result.
  // Check whether the url uses http or https, substring accordingly.
  let shortenedUrl = data.url;
  if (data.url[4] === "s") {
    shortenedUrl = shortenedUrl.substring(8, shortenedUrl.length);
  }
  else {
    shortenedUrl = shortenedUrl.substring(7, shortenedUrl.length);
  }

  if (shortenedUrl[shortenedUrl.length-1] === "/") {
    shortenedUrl = shortenedUrl.substring(0, shortenedUrl.length-1);
  }
  url.innerHTML = shortenedUrl;
  url.className = "resultUrl";

  // Format the rest of the result.
  result.className = "result";
  link.setAttribute("href", data.url);
  link.setAttribute("target", "_blank");
  link.className = "resultLink";
  title.innerHTML = data.title;
  title.className = "resultTitle";
  snippet.innerHTML = data.snippet;
  snippet.className = "resultSnippet";

  // Add the elements together
  result.appendChild(link);
  link.appendChild(url);
  link.appendChild(title);
  result.appendChild(snippet);

  return result;
}

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
async function crawl(urlToCrawl) {
  let phpUrl = "http://localhost/dudaSearch/PHP/crawl.php";
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

// Input: Phrase is the search phrase that the user types.
//        baseUrl is used to generate the sitemap url.
// Output: Response in json format.
// Search the database for all pages related to your search phrase.
async function search(phrase, baseUrl) {
  let phpUrl = "http://localhost/dudaSearch/PHP/search.php";
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
      url: baseUrl,
      phrase: phrase
    }) // body data type must match "Content-Type" header
  });
  //console.log(response.text());
  //return response.text();
  return response.json();
}