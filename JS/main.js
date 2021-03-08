window.addEventListener("DOMContentLoaded", function() {
  const url = "https://www.maintainitall.com/"; //window.location.href;
  const results = document.getElementById("results");
  const pageBar = document.getElementById("pageBar");
  const otherSuggestions = document.getElementById("otherSuggestions");

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
  let postSearchSuggestions = document.getElementById("suggestions"); // Suggestions that are shown after a search.
  const phraseElem = document.getElementById("phrase");

  searchButton.addEventListener("click", (e) => {
    console.log(searchBar.value);
    postSearchSuggestions.className = "";

    if (searchBar.value !== "") {
      search(searchBar.value, url)
      .then(data => {
        console.log(data);
        results.innerHTML = ""; // Clear out the old results to make room for the new.
        pageBar.innerHTML = "";
        phraseElem.innerHTML = data.phrase.text;
        otherSuggestions.innerHTML = "";
        populateSuggestions(data, otherSuggestions, searchBar, searchButton);// (searchData, container, searchBarElem, submitElem)

        if (data.results?.length > 0) {
          // Populate the results container with results.
          populateResults(data, results);
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

  // let preSearchSuggestions = document.getElementById("preSearchSuggestions"); // Suggestions that appear before a search in the dropdown.
  const searchDropdown = document.getElementById("suggestionsDropdown");
  let fetchTimeout;
  searchBar.addEventListener("keyup", (e) => {
    clearTimeout(fetchTimeout);
    fetchTimeout = setTimeout(() => {
      // Display a loading spinner
      searchDropdown.innerHTML = "";
      displayLoadingAnimation(searchDropdown);

      // Populate dropdown with suggestions
      fetchSuggestions(searchBar.value, url, limit = 10)
      .then(data => {
        console.log(data);
        populateSuggestionDropdown(data, searchDropdown, searchBar, searchButton); //(searchData, container, searchBarElem, submitElem)
      })
      .catch(err => {
        console.log("ERROR: ", err);
      });
    }, 200);
  });

  // Show/hide the dropdown when the searchbar is focused/unfocused
  searchBar.addEventListener("focus", (e) => {
    searchDropdown.classList.add("isFocused");
  });
  searchBar.addEventListener("blur", (e) => {
    searchDropdown.classList.remove("isFocused");
  });
});

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