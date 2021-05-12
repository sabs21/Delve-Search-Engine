
let temppagenum = 1; // Governs which page to retrieve when submit is next clicked.

// Input: Object holding result metadata.
// Output: Result element.
// Creates a result element.
const createResult = (data = {url: null, title: null, snippet: null}, classes = {result: "result", link: "link", url: "url", title: "title", snippet: "snippet"}) => {
    let result = document.createElement("div");
    let link = document.createElement("a");
    let url = document.createElement("span");
    let title = document.createElement("h3");
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
    url.className = classes.url;

    // Format the rest of the result.
    result.className = classes.result === null ? "" : classes.result;
    link.setAttribute("href", data.url);
    link.setAttribute("target", "_blank");
    link.className = classes.link === null ? "" : classes.link;
    title.innerHTML = data.title;
    title.className = classes.title === null ? "" : classes.title;
    snippet.innerHTML = data.snippet;
    snippet.className = classes.snippet === null ? "" : classes.snippet;

    // Add the elements together
    result.appendChild(link);
    link.appendChild(url);
    link.appendChild(title);
    result.appendChild(snippet);

    return result;
} // Good

// Input: str is the string to bold all matching terms for.
//        Search phrase obtained from backend. 
// Output: String with bolded terms
// Bold all terms from the search phrase in the given string
const boldKeywords = (str, phrase) => {
    let bolded = str;
    phrase.keywords.forEach(keyword => {
        let innerRegex = " " + keyword.text + "(?=,| )";
        let regex = new RegExp(innerRegex, "gi");

        // This loop adds the bold tags to every regex match it finds.
        // Once bold tags are added, that term won't match the regex again since the space before the term gets removed. This avoids infinite loops.
        let match = regex.exec(bolded);
        while (match) {
            let matchStart = match.index + 1; // We add 1 because the regex includes a space at the beginning. Index.
            let matchEnd = matchStart + keyword.text.length; // Index.
            let firstHalf = bolded.substring(0, matchStart); // Each half excludes the matched string.
            let secondHalf = bolded.substring(matchEnd, bolded.length);
            let keywordFromStr = bolded.substring(matchStart, matchEnd); // Getting the term like this preserves its uppercase and lowercase letters from the original string.

            bolded = firstHalf + "<b>" + keywordFromStr + "</b>" + secondHalf;
            match = regex.exec(bolded); // Check if there are any more matches before the while condition is checked again.
        }
    });
    return bolded;
} // Good

// Input: res is what was obtained from the backend.
//        container is the results container
// Output: None.
// Populate the results container with results.
const populateResults = (data, container) => {
    data.results.forEach(result => {
        // Format each snippet.
        let formattedSnippets = [];
        result.snippets.forEach((snippet, index) => {
            formattedSnippets[index] = {
                text: boldKeywords(snippet.text, data.phrase),
                fromPageContent: snippet.fromPageContent
            };
        });

        // Concatenate all formatted snippets into one.
        let completeSnippet = '';
        let snippetIndex = 0;
        while (completeSnippet.length < 400 && snippetIndex < formattedSnippets.length) {
        completeSnippet += formattedSnippets[snippetIndex].text;
        snippetIndex++;
        }
        /*if (formattedSnippets[0] !== null) {
            completeSnippet = formattedSnippets[0].text;
        }*/
        /*if (result.snippets[0]?.fromPageContent) {
        // If the first part of the snippet is in the middle of the page's content, add ellipses at the beginning of the snippet.
        completeSnippet = '... ';
        }*/
        /*formattedSnippets.forEach(snippet => {
        // Ensure that the snippet doesn't get too long (longer than 350 chars).
        let newLength = completeSnippet.length + snippet.text.length;
        if (newLength < 350) {
            completeSnippet += snippet.text;
        }
        })*/

        /*if (completeSnippet !== '') {
        completeSnippet = completeSnippet.substring(0, completeSnippet.length - 6); // Remove the ending ' ... '
        }*/
        //console.log("completeSnippet", completeSnippet);

        let metadata = {
            url: result.url, 
            title: result.title, 
            snippet: completeSnippet
        }
        //let resultElem = createResult(data);
        container.appendChild(createResult(metadata));
    });
} // Good

// Input: Empty search bar container
// Output: None
// Creates a search bar and adds it to the specified container.
const createSearchBar = (container) => {
    let barWrapper = document.createElement("span");
    barWrapper.id = "barWrapper";
    barWrapper.className = "barButtonAlignment";
    let bar = document.createElement("input");
    bar.id = "searchBar";
    bar.setAttribute("autocomplete", "off");
    let dropdown = document.createElement("div");
    dropdown.id = "searchDropdown";
    let dropdownList = document.createElement("ul");
    dropdownList.id = "predictionItems";

    let button = document.createElement("button");
    button.id = "searchSubmit";
    button.className = "barButtonAlignment";
    button.innerText = "Search";

    barWrapper.append(bar);
    dropdown.append(dropdownList);
    barWrapper.append(dropdown);

    container.append(barWrapper);
    container.append(button);
}

// Input: searchData (what was obtained from the backend)
// Output: Page indicator
// Creates a component that keeps track of what page the user is on out of the maximum amount of pages possible.
const createPageIndicator = (searchData, container) => {
    let pageIndicator = document.createElement("i");
    let currentPage = document.createElement("bold");
    let totalPages = document.createElement("span");

    currentPage.id = "currentPage";
    currentPage.innerText = searchData.page; //"--";
    totalPages.id = "totalPages";
    totalPages.innerText = searchData.totalPages; //"--";

    pageIndicator.innerHTML = "Page " + currentPage.outerHTML + " of " + totalPages.outerHTML;
    container.append(pageIndicator);
}

// Input: searchData (what was obtained from the backend)
//        Submit element
// Output: The contents of the pageBar element.
// Create the page buttons to sift through results.
// The submit element is clicked when a page button is clicked to keep the submit listener code out of this function.
const createPageButtons = (searchData, container, submitElem) => {
    let pageButtons = document.createElement("span");

    // Create each page button
    for (let i = 0; i < searchData.totalPages; i++) {
        let pageButton = document.createElement("button");
        pageButton.className = "pageButton";
        pageButton.innerHTML = i+1;
        pageButton.setAttribute("page", i+1);

        // Page turn listener
        pageButton.addEventListener("click", (e) => {
        temppagenum = e.target.attributes[1].value;
        submitElem.click();
        });

        pageButtons.append(pageButton);
    }

    pageButtons.className = "pageButtons";
    container.append(pageButtons);
}

const createSuggestionButton = (suggestion, searchBarElem, submitElem) => {
    let suggestionButton = document.createElement("button"); 
    suggestionButton.className = "suggestionButton";
    suggestionButton.innerText = suggestion;
    // When the button gets clicked, perform a search using the suggestion.
    suggestionButton.addEventListener("click", (e) => {
        searchBarElem.value = suggestion;
        submitElem.click();
    });
    return suggestionButton;
    }

    // Input: searchData (what was obtained from the backend)
    //        container to place all the suggestion buttons into
    //        search bar element for entering the chosen suggestion into the search bar
    //        submit element for searching for suggestions
    // Output: none.
    // Fill the given container with clickable suggestions.
    const populateSuggestions = (searchData, container, searchBarElem, submitElem) => {
    searchData.suggestions.forEach(item => {
        let text = item.suggestion.text;
        let suggestionButton = createSuggestionButton(text, searchBarElem, submitElem);
        container.append(suggestionButton);
    });
}

// Input: searchData (what was obtained from the backend)
//        container to place all the suggestion buttons into
//        search bar element for entering the chosen suggestion into the search bar
//        submit element for searching the chosen suggestion
// Output: None.
// Populate the suggestions dropdown with suggestions from the backend.
const populateSuggestionDropdown = (searchData, container, searchBarElem, submitElem) => {
    // If the phrase received by the backend doesnt match the current search bar input, return.
    if (searchData.phrase.text !== searchBarElem.value.toLowerCase()) {
        return;
    }

    // Clear out any HTML within the dropdown container
    container.innerHTML = "";

    // Setup the container to act as a dropdown
    container.className = "suggestionsDropdown";
    searchBarHeight = window.getComputedStyle(searchBarElem).getPropertyValue('height');
    container.style.top = searchBarHeight;

    // Prepare the list of suggestions
    let list = document.createElement("ul");
    list.id = "dropdownSuggestions";
    searchData.suggestions.forEach(data => {
        let item = document.createElement("li");
        item.innerText = data.suggestion.text;
        item.className = "dropdownSuggestionItem";
        item.addEventListener("click", (e) => {
        //console.log(e);
        let suggestion = e.target.innerText;
        searchBarElem.value = suggestion;
        submitElem.click();
        });
        list.append(item);
    });
    container.append(list);

    // When there are no suggestions to add to the dropdown, the drop shadow would still display and look bad.
    // This check prevents the drop shadow from appearing when it shouldn't. 
    if (searchData.suggestions.length > 0) {
        container.className = "suggestionsDropdown dropdownDropShadow";
    }

    // Show/hide the dropdown when the searchbar is focused/unfocused
    if (container.getAttribute("listened") !== true) {
        searchBarElem.addEventListener("focus", (e) => {
            container.classList.add("isFocused");
        });
        searchBarElem.addEventListener("blur", (e) => {
            setTimeout(() => {
                container.classList.remove("isFocused");
            }, 500);
        });
        container.addEventListener("click", () => {
            container.classList.add("isFocused");
        });
        container.setAttribute("listened", true);
        container.classList.add("isFocused");
    }
}

const displayLoadingAnimation = (container) => {
    container.innerHTML = `<div id="circularG">
        <div id="circularG_1" class="circularG"></div>
        <div id="circularG_2" class="circularG"></div>
        <div id="circularG_3" class="circularG"></div>
        <div id="circularG_4" class="circularG"></div>
        <div id="circularG_5" class="circularG"></div>
        <div id="circularG_6" class="circularG"></div>
        <div id="circularG_7" class="circularG"></div>
        <div id="circularG_8" class="circularG"></div>
    </div>`;
}

// Input: phpUrl is the url that links to the php script that will crawl the sitemap
//        data holds info to be used by the php script. Such info includes
//        data = { sitemap: "https://www.superiorcleaning.solutions/sitemap.xml" }
// Output: Response in json format
// Send the sitemap url to the php script that will fill the database accordingly
async function crawl(urlToCrawl) {
    let phpUrl = "https://www.devmrk.xyz/delve/crawl.php";
    //let phpUrl = "http://localhost/dudaSearch/PHP/crawl.php";
    if (urlToCrawl[urlToCrawl.length - 1] === '/') {
        urlToCrawl = urlToCrawl.substring(0, urlToCrawl.length - 1);
    }
    let sitemapUrl = urlToCrawl + "/sitemap.xml";
    // Default options are marked with *
    const response = await fetch(phpUrl, {
        method: 'POST', // *GET, POST, PUT, DELETE, etc.
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
        'Content-Type': 'application/json'
        },
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: JSON.stringify({
        sitemap: sitemapUrl
        }) // body data type must match "Content-Type" header
    });
    //console.log(response.text());
    //return response.text();
    return response.json();
} // Good

// Input: Phrase is the search phrase that the user types.
//        baseUrl is used to generate the sitemap url and identify which site to search.
//        page tells the server what page to return. Each page has 10 results.
// Output: Response in json format.
// Search the database for all pages related to your search phrase.
async function search(phrase, baseUrl, options = null) {
    if (options === null) {
        options = { page: temppagenum, filterSymbols: true };
    }
    else {
        if (options.page === null) {
            options.page = temppagenum;
        }
        if (options.filterSymbols === null) {
            options.filterSymbols = true;
        }
        if (options.redirectTo === null) {
            options.redirectTo = false;
        }
    }

    let phpUrl = "https://www.devmrk.xyz/delve/search.php?url=" + baseUrl + "&phrase=" + phrase + "&page=" + options.page + "&filter_symbols=" + options.filterSymbols;
    //let phpUrl = "http://localhost/dudaSearch/PHP/search.php?url=" + baseUrl + "&phrase=" + phrase + "&page=" + options.page + "&filter_symbols=" + options.filterSymbols;
    temppagenum = 1; // Reset the global variable that governs which page to retrieve when submit is next clicked. 

    // Default options are marked with *
    const response = await fetch(phpUrl, {
        method: "GET", // *GET, POST, PUT, DELETE, etc.
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer' // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
    });
    return response.json();
}

// Input: (String) Phrase is the search phrase that the user has typed.
//        (String) baseUrl identifies which site to get suggestions for.
//        (Integer) limit tells the server the maximum amount of suggestions to return.
// Output: Response in json format.
// Search the database for all pages related to your search phrase.
async function fetchSuggestions(phrase, baseUrl, limit = 10) {
let phpUrl = "https://www.devmrk.xyz/delve/suggestions.php?url=" + baseUrl + "&phrase=" + phrase + "&limit=" + limit;
//let phpUrl = "http://localhost/dudaSearch/PHP/suggestions.php?url=" + baseUrl + "&phrase=" + phrase + "&limit=" + limit;
// Default options are marked with *
const response = await fetch(phpUrl, {
    method: 'GET', // *GET, POST, PUT, DELETE, etc.
    mode: 'cors', // no-cors, *cors, same-origin
    cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
    credentials: 'same-origin', // include, *same-origin, omit
    redirect: 'follow', // manual, *follow, error
    referrerPolicy: 'no-referrer' // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
});
return response.json();
}