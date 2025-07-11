import type { Faker } from '@faker-js/faker'

export default (faker: Faker): PlaylistFolder => ({
  type: 'playlist-folders',
  id: faker.string.uuid(),
  name: faker.word.sample(),
})
