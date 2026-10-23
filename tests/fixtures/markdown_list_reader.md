# List reader acceptance

3. Parent with wrapped
   content.

   - Child with [CLI](../reference/v3_cli.md#delete-a-canonical-record)
     - Grandchild

   Parent paragraph after children.
3. Repeated number
8. Deliberate gap

## Deep supported list

- Level 1
  - Level 2
    - Level 3
      - Level 4
        - Level 5
          - Level 6
            - Level 7
              - [Deep link](../reference/v3_cli.md#delete-a-canonical-record) remains readable

## Excessive nesting source

- Level 1
  - Level 2
    - Level 3
      - Level 4
        - Level 5
          - Level 6
            - Level 7
              - Level 8
                - Level 9

## Embedded example source

- Parent
  ```text
<!--example comment-->
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
  ```

## Standalone table retained

| A | B |
| --- | --- |
| one | two |
