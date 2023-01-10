### Usage
```
require('censor.php');
$persianswear = new Censor($pdo);

echo $persianswear->clean('خر خر خر'));