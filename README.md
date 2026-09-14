# HD Development
TYPO3 extension for Frontend developers to use TYPO3 without any special knowledge, just to create frontend content elements over HTML (fluid).

Just create HTML file inside some storage, like fileadmin. Then on place where the developer wants to work add a plugin "Development: Content Element". There specify the template file and if needed some variables.
## Usage of Partials
The "Partials" (`<f:render partial="..." .. />`) is taken from default content element settings in lib.contentElement. As additional settings only for the specific HTML file is possible to append other storages directly inside the flexform where is set the template file.

## Styleguide sync: nested collections

`styleguide:sync` creates inline records of a collection and, since this version, collections inside those records
too (for example the answers of a quiz question), up to 5 levels deep. Define them like a top-level collection, as a
list of rows under the column name; the foreign field (e.g. `foreign_table_parent_uid`) is filled with the uid of the
parent row automatically:

```php
'hyperdigital_quiz_questions' => [
    [
        'foreign_table_parent_uid' => '###UID###',
        'question' => 'Question 1?',
        'answers' => [
            ['foreign_table_parent_uid' => '###UID###', 'answer' => 'Answer A', 'is_correct' => 1],
            ['foreign_table_parent_uid' => '###UID###', 'answer' => 'Answer B', 'is_correct' => 0],
        ],
    ],
],
```

Existing nested records are updated in order on every sync, surplus records are marked deleted, missing ones created.
