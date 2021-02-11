window.addEventListener("DOMContentLoaded", function() {
  const url = window.location.href;
  const results = document.getElementById("results");
  const pageBar = document.getElementById("pageBar");

  let input = document.getElementById("urlInput");
  let crawlBtn = document.getElementById("newSite");

  crawlBtn.addEventListener("click", (e) => {
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
  let suggestions = document.getElementById("suggestions");

  searchButton.addEventListener("click", (e) => {
    console.log(searchBar.value);
    suggestions.className = "";

    if (searchBar.value !== "") {
      search(searchBar.value, "https://www.armorshieldroof.com/")
      .then(data => {
        console.log(data);
        results.innerHTML = ""; // Clear out the old results to make room for the new.
        pageBar.innerHTML = "";
        displayLastSearch(data.phrase);
        populateSuggestions(data.suggestions, data.url);

        if (data.results?.length > 0) {
          // Populate the results container with results.
          populate(data, results);
          pageBar.appendChild(createPageButtons(data));
          setCurrentPage(data.page);
          setTotalPages(data.totalPages);
        }
        else {
          setCurrentPage(0);
          setTotalPages(0);
        }
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }
  });
});

const displayLastSearch = (searchPhrase) => {
  const searchedSuggestion = document.getElementById("searchedSuggestion");
  searchedSuggestion.innerHTML = searchPhrase;
}

// Input: Data from backend
// Output: Array of suggestions (Strings)
const populateSuggestions = (suggestions, url) => {
  const otherSuggestions = document.getElementById("otherSuggestions");
  otherSuggestions.innerHTML = "";
  suggestions.forEach(suggestion => {
    let badge = createSuggestionBadge(suggestion, url);
    otherSuggestions.append(badge);
  });
}

const createSuggestionBadge = (suggestion, url) => {
  let badge = document.createElement("span"); 
  badge.className = "badge suggestion m5";
  badge.innerText = suggestion;
  badge.addEventListener("click", (e) => {
    search(suggestion, url)
  });
  return badge;
}

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
//        terms is an array of words from the search phrase. 
// Output: String with bolded terms
// Bold all terms from the search phrase in the given string
const boldSearchTerms = (str, terms) => {
  let bolded = str;
  terms.forEach(term => {
    let innerRegex = " " + term.keyword + "(?=,| )";
    let regex = new RegExp(innerRegex, "gi");

    // This loop adds the bold tags to every regex match it finds.
    // Once bold tags are added, that term won't match the regex again since the space before the term gets removed. This avoids infinite loops.
    let match = regex.exec(bolded);
    while (match) {
      let termStart = match.index + 1; // We add 1 because the regex includes a space at the beginning. Index.
      let termEnd = termStart + term.keyword.length; // Index.
      let firstHalf = bolded.substring(0, termStart); // Each half excludes the matched string.
      //console.log("firstHalf", firstHalf);
      let secondHalf = bolded.substring(termEnd, bolded.length - 1);
      //console.log("secondHalf", secondHalf);
      let termFromStr = bolded.substring(termStart, termEnd); // Getting the term like this preserves its uppercase and lowercase letters from the original string.
      //console.log("termFromStr", termFromStr);

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
  const resultsElem = document.getElementById("results");
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
      search(searchData.phrase, "https://www.armorshieldroof.com/", page)
      .then(res => {
        console.log(res);
        resultsElem.innerHTML = ""; // Clear out the old results to make room for the new.
        populate(res, resultsElem); // Populate the results container with results.
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
    // Format each snippet.
    let formattedSnippets = [];
    result.snippets.forEach((snippet, index) => {
      //console.log("snippet: ", snippet);
      //console.log("terms: ", res.terms);
      formattedSnippets[index] = {
        text: boldSearchTerms(snippet.text, res.terms),
        fromPageContent: snippet.fromPageContent
      };
    });

    // Concatenate all formatted snippets into one.
    let completeSnippet = '';
    if (result.snippets[0]?.fromPageContent) {
      // If the first part of the snippet is in the middle of the page's content, add ellipses at the beginning of the snippet.
      completeSnippet = '... ';
    }
    formattedSnippets.forEach(snippet => {
      // Ensure that the snippet doesn't get too long (longer than 350 chars).
      let newLength = completeSnippet.length + snippet.text.length;
      if (newLength < 350) {
        completeSnippet += snippet.text + '... ';
      }
    })
    /*if (completeSnippet !== '') {
      completeSnippet = completeSnippet.substring(0, completeSnippet.length - 6); // Remove the ending ' ... '
    }*/
    //console.log("completeSnippet", completeSnippet);

    let data = {
      url: result.url, 
      title: result.title, 
      snippet: completeSnippet
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