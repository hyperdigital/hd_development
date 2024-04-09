# HD Development
TYPO3 extension for Frontend developers to use TYPO3 without any special knowledge, just to create frontend content elements over HTML (fluid).

Just create HTML file inside some storage, like fileadmin. Then on place where the developer wants to work add a plugin "Development: Content Element". There specify the template file and if needed some variables.
## Usage of Partials
The "Partials" (`<f:render partial="..." .. />`) is taken from default content element settings in lib.contentElement. As additional settings only for the specific HTML file is possible to append other storages directly inside the flexform where is set the template file.
