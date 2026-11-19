# VaporAktion2Test

Test plugin answering one question: can a plugin add a variation with 0.00 € as a real basket item, and is that line transferred to the order with 0.00 €?

## How it works

After every basket change (event `AfterBasketChanged`) the plugin checks:

1. Is **Promotion active** switched on and is the customer logged in and in one of the **test customer classes**? If not, nothing happens.
2. If the **trigger variation** is in the basket, the **gift variation** is added with quantity 1.
3. If the trigger is removed, the gift is removed as well.
4. The gift quantity always stays 1.

## Settings

| Setting | Meaning |
|---|---|
| Promotion active | Default: off |
| Test customer classes | IDs, comma separated. Empty = plugin does nothing |
| Trigger variation ID | Variation that triggers the gift |
| Gift variation ID | Variation added as gift (must differ from the trigger) |
| Price mode | "Variation": the gift variation itself has a 0.00 € sales price. "givenPrice": experiment, the plugin passes `givenPrice = 0` |

## Log

Entries appear under **Data » Log** with the integration **VaporAktion2Test** (level *report*, visible by default).
