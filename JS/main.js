window.addEventListener("DOMContentLoaded", function() {
  const url = window.location.href;
  const results = document.getElementById("results");
  const pageBar = document.getElementById("pageBar");

  let input = document.getElementById("urlInput");
  let newPageBtn = document.getElementById("newSite");

  newPageBtn.addEventListener("click", (e) => {
    console.log(input.value);
    if (input.value !== "") {
      crawl(input.value)
      .then(res => {
        console.log(res);
        
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
        results.innerHTML = ""; // Clear out the old results to make room for the new.
        pageBar.innerHTML = "";

        // Populate the results container with results.
        populate(res, results);
        pageBar.appendChild(createPageButtons(res));
        setCurrentPage(res.page);
        setTotalPages(res.totalPages);
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }
  });
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

// Input: str is the string to bold all matching terms for.
//        searchTerms is an array of words from the search phrase. 
// Output: String with bolded terms
// Bold all terms from the search phrase in the given string
const boldSearchTerms = (str, searchTerms) => {
  let bolded = str;
  searchTerms.forEach(term => {
    let innerRegex = " " + term + "(?=,| )";
    let regex = new RegExp(innerRegex, "gi");

    // This loop adds the bold tags to every regex match it finds.
    // Once bold tags are added, that term won't match the regex again since the space before the term gets removed. This avoids infinite loops.
    let match = regex.exec(bolded);
    while (match) {
      let termStart = match.index + 1; // We add 1 because the regex includes a space at the beginning. Index.
      let termEnd = termStart + term.length; // Index.
      let firstHalf = bolded.substring(0, termStart); // Each half excludes the matched string.
      let secondHalf = bolded.substring(termEnd, bolded.length - 1);
      let termFromStr = bolded.substring(termStart, termEnd); // Getting the term like this preserves its uppercase and lowercase letters from the original string.
      
      bolded = firstHalf + "<b>" + termFromStr + "</b>" + secondHalf;
      match = regex.exec(bolded); // Check if there are any more matches before the while condition is checked again.
    }
  });
  return bolded;
}

// Input: searchData (what was obtained from the backend)
// Output: The contents of the pageBar element
// Create the page buttons to sift through results.
const createPageButtons = (searchData) => {
  // This is needed for creating the page turn event listeners
  const results = document.getElementById("results");
  //const searchPhrase = document.getElementById("searchBar").value;

  //let totalPages = Math.ceil(searchData.totalResults / 10);
  let pageButtons = document.createElement("span");

  // Create each page button.
  for (let i = 0; i < searchData.totalPages; i++) {
    let pageButton = document.createElement("button");
    pageButton.className="pageButton";
    pageButton.innerHTML = i+1;
    pageButton.setAttribute("page", i+1);

    // Page turn listener
    pageButton.addEventListener("click", (e) => {
      let page = e.target.attributes[1].value;
      search(searchData.searchPhrase, "https://www.armorshieldroof.com/", page)
      .then(res => {
        console.log(res);
        results.innerHTML = ""; // Clear out the old results to make room for the new.
        populate(res, results); // Populate the results container with results.
        setCurrentPage(res.page);
        setTotalPages(res.totalPages);
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    });

    pageButtons.appendChild(pageButton);
  }

  pageButtons.className = "pageButtons";
  return pageButtons;
}

// Input: res is what was obtained from the backend.
//        container is the results container
// Output: None.
// Populate the results container with results.
const populate = (res, container) => {
  res.results.forEach(result => {
    let data = {
      url: result.url, 
      title: result.title, 
      snippet: boldSearchTerms(result.snippet, res.searchTerms)
    }
    //let resultElem = createResult(data);
    container.appendChild(createResult(data));
  });
}

// Input: page is the page which the user should currently be on.
// Output: None.
// Set the current page value within the pageMonitor.
const setCurrentPage = (page) => {
  const currentPageElem = document.getElementById("currentPage");
  currentPageElem.innerHTML = page;
}

// Input: totalPages is the count of all pages.
// Output: None.
// Set the total page value within the pageMonitor.
const setTotalPages = (totalPages) => {
  const totalPagesElem = document.getElementById("totalPages");
  totalPagesElem.innerHTML = totalPages;
}

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
//        baseUrl is used to generate the sitemap url and identify which site to search.
//        page tells the server what page to return. Each page has 10 results.
// Output: Response in json format.
// Search the database for all pages related to your search phrase.
async function search(phrase, baseUrl, page = 1) {
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
      phrase: phrase,
      page: page
    }) // body data type must match "Content-Type" header
  });
  //console.log(response.text());
  //return response.text();
  return response.json();
}